<?php

namespace Tests\Feature\Admin;

use App\Models\AccessPassword;
use App\Models\Image;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Branding, the editable homepage, and page banner images.
 *
 * Two of these are security assertions rather than feature assertions: a
 * branding image is the one category of media reachable without a page
 * (App\Support\MediaAccess), and the homepage body is the one rich-text
 * document that cannot carry embeds — there is no page row behind it to
 * publish the embedded file, so an embed would render for the owner and
 * 403 for every visitor.
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeImage(string $alt = 'Het logo van de school'): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.png';
        Storage::disk('local')->put($path, 'image-bytes');

        return Image::query()->create([
            'path' => $path,
            'alt_text' => $alt,
            'size_bytes' => 11,
            'mime' => 'image/png',
            'original_filename' => 'logo.png',
        ]);
    }

    /**
     * @return array{0: Topic, 1: Page}
     */
    private function tree(): array
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);

        return [$topic, $page];
    }

    private function unlockCookie(AccessPassword $password): array
    {
        return ['unlock_'.$password->id => substr(hash('sha256', $password->password_hash), 0, 32)];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'site_title' => 'Natuurkunde bij meneer De Vries',
            'site_logo_image_id' => null,
            'site_favicon_image_id' => null,
            'home_heading' => 'Welkom',
            'home_subheading' => 'Al het lesmateriaal op één plek.',
            'home_banner_image_id' => null,
            'home_content' => null,
            ...$overrides,
        ];
    }

    public function test_guests_cannot_reach_the_site_settings_screen()
    {
        $this->get('/admin/instellingen')->assertRedirect('/login');
        $this->put('/admin/instellingen', $this->payload())->assertRedirect('/login');
    }

    public function test_an_empty_settings_table_still_renders_the_defaults()
    {
        $this->assertSame(0, Setting::query()->count());

        $this->actingAs(User::factory()->create())
            ->get('/admin/instellingen')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('admin/site-settings/edit')
                ->where('settings.site_title', config('app.name'))
                ->where('settings.home_heading', SiteSettings::DEFAULTS['home_heading'])
            );
    }

    public function test_the_owner_can_change_the_branding_and_the_homepage_copy()
    {
        $logo = $this->makeImage();

        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload([
                'site_logo_image_id' => $logo->id,
                'home_subheading' => 'Kies een onderwerp.',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('Natuurkunde bij meneer De Vries', SiteSettings::get('site_title'));
        $this->assertSame($logo->id, (int) SiteSettings::get('site_logo_image_id'));

        $this->get('/')->assertInertia(fn ($inertia) => $inertia
            ->component('welcome')
            ->where('home.heading', 'Welkom')
            ->where('home.subheading', 'Kies een onderwerp.')
        );
    }

    public function test_the_site_title_reaches_the_page_before_hydration()
    {
        SiteSettings::put(['site_title' => 'Scheikunde Centraal']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Scheikunde Centraal</title>', false);
    }

    public function test_a_favicon_setting_replaces_the_shipped_icons()
    {
        $favicon = $this->makeImage('Het favicon');

        $response = $this->get('/');
        $response->assertSee('/favicon.ico', false);

        SiteSettings::put(['site_favicon_image_id' => $favicon->id]);

        $response = $this->get('/');
        $response->assertSee(route('images.show', $favicon), false);
        $response->assertDontSee('/favicon.ico', false);
    }

    public function test_a_title_and_logo_are_shared_with_every_inertia_response()
    {
        $logo = $this->makeImage();
        SiteSettings::put(['site_title' => 'Scheikunde Centraal', 'site_logo_image_id' => $logo->id]);

        $this->get('/')->assertInertia(fn ($inertia) => $inertia
            ->where('branding.title', 'Scheikunde Centraal')
            ->where('branding.logo.alt', 'Het logo van de school')
            ->where('branding.logo.url', route('images.show', $logo))
        );
    }

    public function test_a_blank_title_falls_back_to_the_application_name()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload(['site_title' => '']))
            ->assertSessionHasErrors('site_title');

        // And a row that somehow ends up empty still renders a name rather
        // than an untitled tab.
        Setting::query()->updateOrCreate(['key' => 'site_title'], ['value' => '']);

        $this->assertSame(config('app.name'), SiteSettings::get('site_title'));
    }

    public function test_an_image_id_saved_from_the_form_reads_back_as_an_integer()
    {
        $logo = $this->makeImage();
        $admin = User::factory()->create();

        // A form field arrives as the string "7". Every reader compares ids
        // strictly — in_array(..., true) here, `===` in the picker — so a
        // string would leave the saved image looking unselected and, worse,
        // unpublished.
        $this->actingAs($admin)
            ->put('/admin/instellingen', $this->payload(['site_logo_image_id' => (string) $logo->id]));

        $this->assertSame([$logo->id], SiteSettings::brandingImageIds());
        $this->assertSame($logo->id, SiteSettings::get('site_logo_image_id'));

        $this->actingAs($admin)
            ->get('/admin/instellingen')
            ->assertInertia(fn ($inertia) => $inertia
                ->where('settings.site_logo_image_id', $logo->id)
            );

        $this->get(route('images.show', $logo))->assertOk();
    }

    public function test_an_unknown_setting_key_is_ignored()
    {
        SiteSettings::put(['site_title' => 'Wel', 'smtp_password' => 'niet']);

        $this->assertNull(Setting::query()->find('smtp_password'));
    }

    public function test_a_branding_image_must_exist()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload(['site_logo_image_id' => 9999]))
            ->assertSessionHasErrors('site_logo_image_id');
    }

    // --- The homepage body -------------------------------------------------

    public function test_the_homepage_body_is_whitelisted_like_a_page_body()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload(['home_content' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Klik hier', 'marks' => [
                            ['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']],
                        ]],
                    ],
                ]],
            ]]))
            ->assertSessionHasNoErrors();

        $stored = SiteSettings::get('home_content');

        $this->assertSame('Klik hier', $stored['content'][0]['content'][0]['text']);
        $this->assertArrayNotHasKey('marks', $stored['content'][0]['content'][0]);
    }

    public function test_embeds_are_stripped_from_the_homepage_body()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload(['home_content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Welkom!']]],
                    ['type' => 'fileEmbed', 'attrs' => ['ulid' => (string) Str::ulid()]],
                    ['type' => 'youtubeEmbed', 'attrs' => ['videoId' => 'dQw4w9WgXcQ']],
                    ['type' => 'imageGallery', 'attrs' => ['ulids' => []]],
                ],
            ]]))
            ->assertSessionHasNoErrors();

        $stored = SiteSettings::get('home_content');
        $types = array_column($stored['content'], 'type');

        $this->assertSame(['paragraph'], $types);
    }

    public function test_the_homepage_body_survives_a_save_that_does_not_touch_it()
    {
        SiteSettings::put(['home_content' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Welkom!']]],
        ]]]);

        // A payload whose only rules-bearing keys are the copy fields must not
        // erase the document — the validated()-drops-unruled-keys trap.
        $this->actingAs(User::factory()->create())
            ->put('/admin/instellingen', $this->payload([
                'home_heading' => 'Lesmateriaal',
                'home_content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Welkom!']]],
                ]],
            ]));

        $stored = SiteSettings::get('home_content');

        $this->assertSame('Welkom!', $stored['content'][0]['content'][0]['text']);
    }

    // --- Media access ------------------------------------------------------

    public function test_a_branding_image_is_readable_by_anyone_while_a_setting_points_at_it()
    {
        $logo = $this->makeImage();

        // Nothing points at it yet: private, like every other upload.
        $this->get(route('images.show', $logo))->assertForbidden();

        SiteSettings::put(['site_logo_image_id' => $logo->id]);

        $this->get(route('images.show', $logo))->assertOk();
    }

    public function test_clearing_a_branding_setting_makes_the_image_private_again()
    {
        $logo = $this->makeImage();
        SiteSettings::put(['site_logo_image_id' => $logo->id]);
        $this->get(route('images.show', $logo))->assertOk();

        SiteSettings::put(['site_logo_image_id' => null]);

        $this->get(route('images.show', $logo))->assertForbidden();
    }

    public function test_branding_does_not_publish_any_other_image()
    {
        $logo = $this->makeImage();
        $other = $this->makeImage('Een andere afbeelding');
        SiteSettings::put(['site_logo_image_id' => $logo->id]);

        $this->get(route('images.show', $other))->assertForbidden();
    }

    public function test_an_image_in_use_as_branding_cannot_be_deleted()
    {
        $logo = $this->makeImage();
        SiteSettings::put(['site_favicon_image_id' => $logo->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.media.images.destroy', $logo))
            ->assertSessionHas('error');

        $this->assertModelExists($logo);
        Storage::disk('local')->assertExists($logo->path);
    }

    // --- Hero images -------------------------------------------------------

    public function test_a_hero_image_is_published_by_its_page()
    {
        [, $page] = $this->tree();
        $hero = $this->makeImage('De ringen van Saturnus');

        $this->get(route('images.show', $hero))->assertForbidden();

        $page->update(['hero_image_id' => $hero->id]);

        $this->get(route('images.show', $hero))->assertOk();
        $this->get('/natuurkunde/de-planeten')->assertInertia(fn ($inertia) => $inertia
            ->where('page.hero.url', route('images.show', $hero))
            ->where('page.hero.alt', 'De ringen van Saturnus')
        );
    }

    public function test_a_hero_image_inherits_its_pages_password()
    {
        [$topic, $page] = $this->tree();
        $hero = $this->makeImage('De ringen van Saturnus');
        $page->update(['hero_image_id' => $hero->id]);

        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');
        $topic->update(['access_password_id' => $password->id]);

        $this->get(route('images.show', $hero))->assertForbidden();

        $this->withCookies($this->unlockCookie($password))
            ->get(route('images.show', $hero))
            ->assertOk();
    }

    public function test_a_hero_image_on_a_hidden_page_is_still_served()
    {
        [, $page] = $this->tree();
        $hero = $this->makeImage();
        $page->update(['hero_image_id' => $hero->id, 'is_hidden' => true]);

        // Hidden is a draft, not a secret: the page still renders at its
        // direct URL, so refusing its banner would render it broken rather
        // than private.
        $this->get(route('images.show', $hero))->assertOk();
    }

    public function test_an_image_in_use_as_a_hero_cannot_be_deleted()
    {
        [, $page] = $this->tree();
        $hero = $this->makeImage();
        $page->update(['hero_image_id' => $hero->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.media.images.destroy', $hero))
            ->assertSessionHas('error');

        $this->assertModelExists($hero);
    }

    public function test_a_page_can_be_given_and_cleared_a_hero_image()
    {
        [$topic, $page] = $this->tree();
        $hero = $this->makeImage();
        $admin = User::factory()->create();

        $fields = [
            'title' => 'De Planeten',
            'slug' => 'de-planeten',
            'topic_id' => $topic->id,
            'sort_order' => 0,
            'is_hidden' => false,
        ];

        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), [...$fields, 'hero_image_id' => $hero->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($hero->id, $page->fresh()->hero_image_id);

        // The picker submits an empty string for "none".
        $this->actingAs($admin)
            ->put(route('admin.pages.update', $page), [...$fields, 'hero_image_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($page->fresh()->hero_image_id);
    }
}
