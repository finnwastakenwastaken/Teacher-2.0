<?php

namespace Tests\Feature\Media;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Topic;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * What a library item is *for*, on the media screen.
 *
 * Derived, never authored. The answer already exists in the rows that decide
 * what is published — page_media_references for what a page shows,
 * page_downloads for what it hands out — so a flag the owner had to set would
 * be a second place for the truth to live, and it would force a lie the first
 * time a diagram was both embedded in a lesson and offered as a printable
 * handout. Both flags can be true at once, deliberately.
 */
class MediaLibraryUsageTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug = 'de-planeten'): Page
    {
        $topic = Topic::query()->firstOrCreate(
            ['parent_id' => null, 'slug' => 'natuurkunde'],
            ['title' => 'Natuurkunde']
        );

        return Page::query()->create([
            'title' => 'De Planeten', 'slug' => $slug, 'topic_id' => $topic->id,
        ]);
    }

    private function image(string $filename): Image
    {
        $path = 'images/'.Str::ulid().'.webp';
        Storage::disk('local')->put($path, 'bytes');

        return Image::query()->create([
            'path' => $path, 'alt_text' => 'Een beschrijving',
            'width' => 10, 'height' => 10, 'size_bytes' => 5,
            'mime' => 'image/webp', 'original_filename' => $filename,
        ]);
    }

    private function file(string $filename): MediaFile
    {
        $path = 'media/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        return MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf', 'size_bytes' => 5,
            'original_filename' => $filename,
        ]);
    }

    public function test_the_library_says_what_each_item_is_used_for()
    {
        Storage::fake('local');

        $unused = $this->image('ongebruikt.webp');
        $embedded = $this->image('in-de-les.webp');
        $offered = $this->image('poster.webp');
        $both = $this->image('diagram.webp');
        $banner = $this->image('banner.webp');

        $unusedFile = $this->file('ongebruikt.pdf');
        $offeredFile = $this->file('werkblad.pdf');

        $page = $this->page();
        $page->update(['hero_image_id' => $banner->id]);
        $page->writeContent(['type' => 'doc', 'content' => [
            ['type' => 'imageAside', 'attrs' => ['ulid' => $embedded->ulid, 'side' => 'right', 'size' => 'medium']],
            ['type' => 'imageAside', 'attrs' => ['ulid' => $both->ulid, 'side' => 'left', 'size' => 'medium']],
        ]]);
        $page->downloads()->create(['image_id' => $offered->id]);
        $page->downloads()->create(['image_id' => $both->id]);
        $page->downloads()->create(['media_file_id' => $offeredFile->id]);

        // Newest first, so the assertions are keyed by ULID rather than by
        // position — the order of five uploads is not what this is about.
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.media.index'));

        $response->assertInertia(function (AssertableInertia $inertia) use (
            $unused, $embedded, $offered, $both, $banner, $unusedFile, $offeredFile
        ) {
            $images = collect($inertia->toArray()['props']['images'])->keyBy('ulid');
            $files = collect($inertia->toArray()['props']['files'])->keyBy('ulid');

            $this->assertFalse($images[$unused->ulid]['shownOnPage']);
            $this->assertFalse($images[$unused->ulid]['offeredAsDownload']);

            $this->assertTrue($images[$embedded->ulid]['shownOnPage']);
            $this->assertFalse($images[$embedded->ulid]['offeredAsDownload']);

            $this->assertFalse($images[$offered->ulid]['shownOnPage']);
            $this->assertTrue($images[$offered->ulid]['offeredAsDownload']);

            // The case a single authored flag could not have represented.
            $this->assertTrue($images[$both->ulid]['shownOnPage']);
            $this->assertTrue($images[$both->ulid]['offeredAsDownload']);

            // A banner is page furniture rather than a body embed, and
            // calling it unused would invite the owner to delete it.
            $this->assertTrue($images[$banner->ulid]['shownOnPage']);

            $this->assertFalse($files[$unusedFile->ulid]['shownOnPage']);
            $this->assertFalse($files[$unusedFile->ulid]['offeredAsDownload']);
            $this->assertTrue($files[$offeredFile->ulid]['offeredAsDownload']);
        });
    }

    /**
     * Branding is not "shown on a page" — the logo and favicon are reached
     * with no page context at all, which is exactly why they are the one
     * special case in App\Support\MediaAccess. The screen says so honestly
     * rather than borrowing a badge that would be wrong, and deleting one is
     * still blocked with its own message.
     */
    public function test_a_branding_image_is_not_reported_as_shown_on_a_page()
    {
        Storage::fake('local');
        $logo = $this->image('logo.webp');
        SiteSettings::put(['site_logo_image_id' => $logo->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.media.index'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('images.0.shownOnPage', false)
                ->where('images.0.offeredAsDownload', false)
            );

        $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.images.destroy', $logo))
            ->assertSessionHas('error');
    }
}
