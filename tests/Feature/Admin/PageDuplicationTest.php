<?php

namespace Tests\Feature\Admin;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_duplicate_a_page()
    {
        $page = $this->page();

        $this->post(route('admin.pages.duplicate', $page))->assertRedirect(route('login'));
        $this->assertSame(1, Page::query()->count());
    }

    public function test_duplicating_lands_on_the_copy_and_keeps_the_original()
    {
        $page = $this->page();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.duplicate', $page))
            ->assertRedirect();

        $copy = Page::query()->where('id', '!=', $page->id)->sole();

        $this->assertSame('De Planeten (kopie)', $copy->title);
        $this->assertSame('de-planeten-kopie', $copy->slug);
        $this->assertSame($page->topic_id, $copy->topic_id);
        $this->assertTrue($page->fresh()->exists);
    }

    /**
     * A duplicate says exactly what another page already says, so publishing
     * it the moment it exists would put two identical pages in front of
     * students.
     */
    public function test_the_copy_starts_hidden()
    {
        $page = $this->page(['is_hidden' => false]);

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $this->assertTrue(Page::query()->where('id', '!=', $page->id)->sole()->is_hidden);
    }

    public function test_the_body_is_copied_and_republishes_its_media()
    {
        $image = $this->image();
        $page = $this->page();
        $page->writeContent([
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Uitleg over banen']]],
                ['type' => 'imageGallery', 'attrs' => ['ulids' => [$image->ulid]]],
            ],
        ]);

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $copy = Page::query()->where('id', '!=', $page->id)->sole();

        $this->assertSame('Uitleg over banen', $copy->content['content'][0]['content'][0]['text']);
        // content_text is derived, never copied — it must describe what is
        // actually stored on the copy.
        $this->assertStringContainsString('Uitleg over banen', $copy->content_text);
        // The reference rows are what keep the image reachable from the copy.
        $this->assertSame(1, $copy->mediaReferences()->count());
    }

    public function test_downloads_are_copied_with_their_level_tags()
    {
        $file = $this->mediaFile();
        $havo = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);
        $vwo = EducationLevel::query()->create(['name' => 'VWO', 'slug' => 'vwo', 'sort_order' => 1]);

        $page = $this->page();
        $download = $page->downloads()->create([
            'media_file_id' => $file->id, 'label' => 'Werkblad 3', 'sort_order' => 0,
        ]);
        $download->educationLevels()->sync([$havo->id, $vwo->id]);

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $copied = Page::query()->where('id', '!=', $page->id)->sole()->downloads()->sole();

        $this->assertSame($file->id, $copied->media_file_id);
        $this->assertSame('Werkblad 3', $copied->label);
        $this->assertEqualsCanonicalizing(
            ['HAVO', 'VWO'],
            $copied->educationLevels->pluck('name')->all()
        );
    }

    /**
     * The tally belongs to the attachment that earned it. Starting a fresh
     * page at last year's number is a lie the owner cannot correct.
     */
    public function test_the_download_tally_starts_at_zero()
    {
        $file = $this->mediaFile();
        $page = $this->page();
        $download = $page->downloads()->create(['media_file_id' => $file->id, 'sort_order' => 0]);
        $download->forceFill(['downloads_count' => 42])->save();

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $copied = Page::query()->where('id', '!=', $page->id)->sole()->downloads()->sole();

        $this->assertSame(0, $copied->downloads_count);
        $this->assertSame(42, $download->fresh()->downloads_count);
    }

    public function test_the_hero_image_and_password_come_along()
    {
        $image = $this->image();
        $password = AccessPassword::createWithPassword('5 VWO', 'zwaartekracht');
        $page = $this->page(['hero_image_id' => $image->id, 'access_password_id' => $password->id]);

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $copy = Page::query()->where('id', '!=', $page->id)->sole();

        $this->assertSame($image->id, $copy->hero_image_id);
        $this->assertSame($password->id, $copy->access_password_id);
    }

    public function test_duplicating_twice_does_not_collide()
    {
        $page = $this->page();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.pages.duplicate', $page))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.pages.duplicate', $page))->assertSessionHasNoErrors();

        $this->assertSame(3, Page::query()->count());
        $this->assertEqualsCanonicalizing(
            ['de-planeten', 'de-planeten-kopie', 'de-planeten-kopie-2'],
            Page::query()->pluck('slug')->all()
        );
    }

    /**
     * Sibling uniqueness spans both tables, and the Postgres trigger makes a
     * wrong guess an exception rather than a warning.
     */
    public function test_a_sibling_topic_already_using_the_copy_slug_is_stepped_over()
    {
        $page = $this->page();
        Topic::query()->create([
            'title' => 'Kopie', 'slug' => 'de-planeten-kopie', 'parent_id' => $page->topic_id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.duplicate', $page))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'de-planeten-kopie-2',
            Page::query()->where('id', '!=', $page->id)->sole()->slug
        );
    }

    public function test_the_copy_joins_the_end_of_its_topics_list()
    {
        $page = $this->page();
        Page::query()->create([
            'title' => 'Zwaartekracht', 'slug' => 'zwaartekracht',
            'topic_id' => $page->topic_id, 'sort_order' => 7,
        ]);

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $this->assertSame(8, Page::query()->where('slug', 'de-planeten-kopie')->sole()->sort_order);
    }

    /**
     * A copy is a new page at a new address, not a page that moved. Writing a
     * redirect here would send visitors from the original to the duplicate.
     */
    public function test_duplicating_leaves_no_slug_redirect_behind()
    {
        $page = $this->page();

        $this->actingAs(User::factory()->create())->post(route('admin.pages.duplicate', $page));

        $this->assertDatabaseCount('slug_redirects', 0);
    }

    private function image(): Image
    {
        return Image::query()->create([
            'path' => 'images/'.uniqid().'.png',
            'alt_text' => 'Een diagram',
            'size_bytes' => 5,
            'mime' => 'image/png',
            'original_filename' => 'diagram.png',
        ]);
    }

    private function mediaFile(): MediaFile
    {
        return MediaFile::query()->create([
            'path' => 'files/'.uniqid().'.pdf',
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);
    }

    private function page(array $overrides = []): Page
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);

        return Page::query()->create([
            'title' => 'De Planeten',
            'slug' => 'de-planeten',
            'topic_id' => $topic->id,
            'description' => 'Ons zonnestelsel.',
            'sort_order' => 0,
            ...$overrides,
        ]);
    }
}
