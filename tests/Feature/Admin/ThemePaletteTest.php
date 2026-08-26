<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use App\Support\ThemePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The owner's own colours.
 *
 * Two halves, and only one of them can be tested here. The contrast gate is
 * measured in a browser (resources/js/lib/palette-contrast.ts) because several
 * semantic roles are a color-mix() this process has no renderer for, and
 * because the project's own rule is that contrast is measured against the real
 * rendered stack rather than estimated from the palette — the pass that
 * estimated was wrong about five roles at once.
 *
 * What is testable here is the half that has to hold whatever the browser
 * does: a value that is not a colour never becomes one, an override is only
 * stored while it differs from the shipped palette, and what does get stored
 * reaches the document before anything hydrates.
 */
class ThemePaletteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, array<string, string>>
     */
    private function payload(array $overrides = []): array
    {
        return ['palette' => [...ThemePalette::defaults(), ...$overrides]];
    }

    public function test_guests_cannot_reach_the_palette_editor()
    {
        $this->get('/admin/kleuren')->assertRedirect('/login');
        $this->put('/admin/kleuren', $this->payload())->assertRedirect('/login');
    }

    /**
     * The names in PHP and the colours in the stylesheet are one list read two
     * ways. A renamed entry has to fail here rather than quietly disappear
     * from the screen — which is what would happen, because forInertia() skips
     * an entry the stylesheet does not declare.
     */
    public function test_every_declared_entry_exists_in_the_stylesheet_and_the_other_way_round()
    {
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));

        preg_match_all(
            '/'.preg_quote(ThemePalette::PREFIX, '/').'([a-z0-9-]+)\s*:\s*#[0-9a-fA-F]{3,8}\s*;/',
            $stylesheet,
            $matches,
        );

        $declared = array_values(array_unique($matches[1]));
        $named = ThemePalette::keys();

        sort($declared);
        sort($named);

        $this->assertSame(
            $declared,
            $named,
            'App\Support\ThemePalette::ENTRIES and the raw palette in resources/css/app.css have drifted apart.'
        );

        $this->assertCount(count($named), ThemePalette::defaults());
    }

    public function test_every_entry_is_named_in_both_locales()
    {
        foreach (ThemePalette::ENTRIES as $key => $label) {
            foreach (['nl', 'en'] as $locale) {
                $this->assertNotSame(
                    $label,
                    trans($label, [], $locale),
                    "The palette entry {$key} has no name in lang/{$locale}."
                );
            }
        }
    }

    public function test_a_fresh_install_offers_the_shipped_palette_and_overrides_nothing()
    {
        $this->assertSame(0, Setting::query()->count());

        $this->actingAs(User::factory()->create())
            ->get('/admin/kleuren')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia
                ->component('admin/theme/edit')
                ->has('palette', count(ThemePalette::ENTRIES))
                ->where('palette.0.key', 'blue')
                ->where('palette.0.default', ThemePalette::defaults()['blue'])
                ->where('palette.0.value', ThemePalette::defaults()['blue'])
                ->where('palette.0.overridden', false)
            );

        // Nothing overridden means nothing emitted: the stylesheet decides.
        $this->assertNull(ThemePalette::style());
        $this->get('/')->assertDontSee(':root:root', false);
    }

    public function test_only_the_entries_that_differ_are_stored()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/kleuren', $this->payload(['blue' => '#123456']))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        // An entry equal to the shipped colour is not an override. Storing it
        // would pin it: a later version that improves the palette would leave
        // this site behind on a value its owner never chose.
        $this->assertSame(['blue' => '#123456'], SiteSettings::get(ThemePalette::SETTING));
    }

    public function test_the_override_reaches_the_document_before_hydration()
    {
        SiteSettings::put([ThemePalette::SETTING => ['blue' => '#123456']]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('--p-blue: #123456;', false);
        // The doubled selector is what makes this win regardless of whether
        // app.css is a <link> or is injected from JavaScript by the dev
        // server. See ThemePalette::style().
        $response->assertSee(':root:root {', false);
    }

    /**
     * app.blade.php paints the page background before app.css is parsed, so a
     * rebranded background has to move with it or every cold load flashes the
     * shipped colour first — which is the exact thing that block exists to
     * prevent.
     */
    public function test_overriding_the_page_background_moves_the_pre_paint_rule_too()
    {
        SiteSettings::put([ThemePalette::SETTING => [
            'grey-50' => '#fffdf5',
            'slate-deep' => '#101820',
        ]]);

        $response = $this->get('/');

        $response->assertSee("html {\n    background-color: #fffdf5;\n}", false);
        $response->assertSee("html.dark {\n    background-color: #101820;\n}", false);
    }

    public function test_resetting_everything_deletes_the_row_rather_than_storing_the_defaults()
    {
        SiteSettings::put([ThemePalette::SETTING => ['blue' => '#123456']]);
        $this->assertNotNull(Setting::query()->find(ThemePalette::SETTING));

        $this->actingAs(User::factory()->create())
            ->put('/admin/kleuren', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(Setting::query()->find(ThemePalette::SETTING));
        $this->assertNull(ThemePalette::style());
    }

    /**
     * The security half, and the reason it is not optional even though the
     * contrast gate lives in the browser: this value is written straight into
     * a <style> block. Rejected, never sanitised.
     */
    #[DataProvider('notColours')]
    public function test_a_value_that_is_not_a_colour_is_refused(string $value)
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/kleuren', $this->payload(['blue' => $value]))
            ->assertSessionHasErrors('palette.blue');

        $this->assertNull(Setting::query()->find(ThemePalette::SETTING));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notColours(): array
    {
        return [
            'closes the style element' => ['#fff</style><script>alert(1)</script>'],
            'a bare angle bracket' => ['#fff<'],
            'a quote' => ['#fff"'],
            'opens a url()' => ['url(https://example.com/x.png)'],
            'a url after a colour' => ['#fff; background-image: url(//evil.test/x)'],
            'a css function' => ['color-mix(in srgb, red, blue)'],
            'a named colour' => ['red'],
            'an rgb() colour' => ['rgb(0 0 0)'],
            'a brace' => ['#fff}'],
            'nothing at all' => ['#'],
            'too many digits' => ['#0123456789'],
            'a newline after a colour' => ["#00a8ff\n}html{display:none"],
        ];
    }

    /**
     * A settings row can also arrive from a restored archive or from somebody
     * with psql, so the check on the way in is not the only one that matters.
     */
    public function test_a_bad_row_written_directly_never_reaches_the_page()
    {
        Setting::query()->updateOrCreate(['key' => ThemePalette::SETTING], ['value' => [
            'blue' => '#123456',
            'purple' => '</style><script>alert(1)</script>',
            'not-a-palette-entry' => '#ff0000',
            // Nothing trims this path — TrimStrings only touches a request —
            // so it is the one that proves the D modifier on the pattern.
            // Without it PCRE lets `$` match before a trailing newline, and a
            // newline is the one character that turns one declaration into
            // two.
            'green' => "#00ff00\n    background-image: url(//evil.test/x);",
        ]]);

        $this->assertSame(['blue' => '#123456'], ThemePalette::overrides());

        $response = $this->get('/');

        $response->assertSee('--p-blue: #123456;', false);
        $response->assertDontSee('alert(1)', false);
        $response->assertDontSee('not-a-palette-entry', false);
        $response->assertDontSee('evil.test', false);
    }

    /**
     * Laravel trims request input before validation, so a value the owner
     * pasted with a stray space or newline around it is a colour by the time
     * the rule sees it. That is the right outcome — it is plainly what they
     * meant — and it is worth pinning, because the other half of the same
     * story is the test above: the read path has no TrimStrings, and there the
     * same bytes are refused outright.
     */
    public function test_surrounding_whitespace_in_the_form_is_trimmed_rather_than_refused()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/kleuren', $this->payload(['blue' => " #00c8ff\n"]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['blue' => '#00c8ff'], SiteSettings::get(ThemePalette::SETTING));
    }

    public function test_an_unknown_entry_in_the_form_is_ignored()
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/kleuren', ['palette' => [
                'blue' => '#123456',
                'brand' => '#ff0000',
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['blue' => '#123456'], SiteSettings::get(ThemePalette::SETTING));
    }

    /**
     * The screen reports an override as an override, so the reset control
     * knows there is something to reset.
     */
    public function test_an_overridden_entry_is_reported_as_one()
    {
        SiteSettings::put([ThemePalette::SETTING => ['blue' => '#123456']]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/kleuren')
            ->assertInertia(fn ($inertia) => $inertia
                ->where('palette.0.key', 'blue')
                ->where('palette.0.value', '#123456')
                ->where('palette.0.default', ThemePalette::defaults()['blue'])
                ->where('palette.0.overridden', true)
                ->where('palette.1.overridden', false)
            );
    }
}
