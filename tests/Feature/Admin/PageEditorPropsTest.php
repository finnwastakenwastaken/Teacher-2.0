<?php

namespace Tests\Feature\Admin;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
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
 * The page editor screen is assembled from several independent features, so
 * the props it needs are easy to drop when one of them changes. A missing
 * prop is a blank section rather than an error, which is exactly the kind of
 * breakage nothing else notices.
 */
class PageEditorPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_screen_receives_everything_it_renders()
    {
        Storage::fake('local');

        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);

        $level = EducationLevel::query()->create(['name' => 'HAVO', 'slug' => 'havo', 'sort_order' => 0]);
        AccessPassword::createWithPassword('5 VWO', 'geheim');

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        $file = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'werkblad.pdf',
        ]);

        $download = $page->downloads()->create(['media_file_id' => $file->id, 'label' => 'Werkblad']);
        $download->educationLevels()->sync([$level->id]);

        // Embedded separately from the download, via the body — the two are
        // deliberately independent (page_media_references vs page_downloads,
        // see Page::writeContent()), so a second file proves mediaLibrary
        // reflects only what the body shows, not the download.
        $embeddedPath = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($embeddedPath, 'bytes');
        $embedded = MediaFile::query()->create([
            'path' => $embeddedPath, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'bijlage.pdf',
        ]);
        $page->writeContent([
            'type' => 'doc',
            'content' => [['type' => 'fileEmbed', 'attrs' => ['ulid' => $embedded->ulid]]],
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('admin/pages/edit')
                ->has('mediaLibrary.images', 0)
                // Only the embedded file, resolved from page_media_references —
                // not the whole media_files table, and not the file attached
                // as a download below. The picker dialogs ask
                // App\Http\Controllers\Admin\MediaSearchController for
                // anything else, a page of matches at a time.
                ->has('mediaLibrary.files', 1)
                ->where('mediaLibrary.files.0.id', $embedded->id)
                ->where('mediaLibrary.files.0.ulid', $embedded->ulid)
                ->has('passwords', 1)
                ->has('educationLevels', 1)
                ->has('downloads', 1)
                ->where('downloads.0.label', 'Werkblad')
                ->where('downloads.0.educationLevelIds', [$level->id])
                ->where('downloads.0.mediaFileId', $file->id)
                // The embedded file is not attached as a download, so it is
                // still something the "choose a file" dialog could offer —
                // this is the boolean the downloads section checks instead of
                // being sent the whole library to work that out itself.
                ->where('attachableFilesAvailable', true)
                // The editor uploads too, so it needs the same ceiling the
                // media screen shows.
                ->where('uploadMaxBytes', (int) config('media.max_bytes'))
            );
    }

    public function test_the_editor_payload_no_longer_scales_with_the_size_of_the_library()
    {
        Storage::fake('local');

        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);

        // Representative of "a few hundred files" — the technical reference's own estimate
        // for when the old, since-removed shape cost hundreds of kilobytes.
        // None of these are embedded in the body or attached as a download,
        // so a payload that only sends what the page actually shows must not
        // grow with them.
        foreach (range(1, 150) as $i) {
            $imagePath = "images/{$i}.webp";
            Storage::disk('local')->put($imagePath, 'bytes');
            Image::query()->create([
                'path' => $imagePath,
                'alt_text' => "Een uitgebreide beschrijving die bij een echte upload hoort, nummer {$i}",
                'width' => 800, 'height' => 600, 'size_bytes' => 12345,
                'mime' => 'image/webp',
                'original_filename' => "vakantiefoto-met-een-realistisch-lange-bestandsnaam-{$i}.webp",
            ]);

            $filePath = "media/{$i}.pdf";
            Storage::disk('local')->put($filePath, 'bytes');
            MediaFile::query()->create([
                'path' => $filePath, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
                'size_bytes' => 234567,
                'original_filename' => "werkblad-hoofdstuk-met-oefeningen-en-antwoorden-{$i}.pdf",
            ]);
        }

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.pages.edit', $page));
        $response->assertOk();
        $newBytes = strlen((string) $response->getContent());

        // What the old mediaLibrary shape alone would have cost: the whole
        // images and media_files tables, each row shaped exactly the way
        // PageController::edit used to send it before mediaLibrary was
        // narrowed to only what the body embeds.
        // Reconstructed here rather than measured against a reverted
        // controller, so the comparison stays inside one self-contained test.
        $oldMediaLibraryBytes = strlen((string) json_encode([
            'images' => Image::query()->latest()->get(['ulid', 'alt_text', 'original_filename'])
                ->map(fn (Image $image) => [
                    'ulid' => $image->ulid,
                    'alt_text' => $image->alt_text,
                    'original_filename' => $image->original_filename,
                    'url' => route('images.show', $image),
                ]),
            'files' => MediaFile::query()->latest()->get(['id', 'ulid', 'kind', 'mime', 'size_bytes', 'original_filename'])
                ->map(fn (MediaFile $file) => [
                    'id' => $file->id,
                    'ulid' => $file->ulid,
                    'kind' => $file->kind,
                    'mime' => $file->mime,
                    'size_bytes' => $file->size_bytes,
                    'original_filename' => $file->original_filename,
                    'url' => route('media.show', $file),
                ]),
        ]));

        fwrite(STDERR, sprintf(
            "\n[TODO 8] 150 images + 150 files, none embedded: old mediaLibrary block alone was %s bytes. ".
            "New full /admin/pages/{id}/edit response is %s bytes.\n",
            number_format($oldMediaLibraryBytes),
            number_format($newBytes),
        ));

        // The new response is the *entire* edit screen — settings form,
        // editor chrome, banner picker and all — not just mediaLibrary, and
        // it is still smaller than the old mediaLibrary block was on its own.
        $this->assertLessThan($oldMediaLibraryBytes, $newBytes);

        // The banner picker was the last thing on this screen still carrying
        // the whole library; it now carries only what the banner points at,
        // which here is nothing. Asserted separately from the byte count
        // because a size comparison would still pass if it came back.
        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('heroImage', null)
        );
    }

    /**
     * The other half of the same change, and the screen it mattered most on:
     * three pickers render here, and each one used to be handed the library.
     */
    public function test_the_settings_screen_sends_only_the_images_it_points_at()
    {
        Storage::fake('local');

        $images = collect(range(1, 20))->map(function (int $i) {
            Storage::disk('local')->put("images/{$i}.webp", 'bytes');

            return Image::query()->create([
                'path' => "images/{$i}.webp",
                'alt_text' => "Beschrijving {$i}",
                'width' => 800, 'height' => 600, 'size_bytes' => 12345,
                'mime' => 'image/webp',
                'original_filename' => "afbeelding-{$i}.webp",
            ]);
        });

        $this->actingAs(User::factory()->create());

        // Nothing chosen yet: the screen renders three empty pickers and
        // sends no images at all.
        $this->get(route('admin.site-settings.edit'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->count('selectedImages', 0)
            );

        SiteSettings::put([
            'site_logo_image_id' => $images[0]->id,
            'home_banner_image_id' => $images[1]->id,
        ]);

        $this->get(route('admin.site-settings.edit'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                // Two, not twenty — and never more than three however large
                // the library grows, because that is how many settings hold
                // an image id.
                ->count('selectedImages', 2)
                ->where('selectedImages.0.id', $images[0]->id)
            );
    }

    public function test_the_downloads_section_is_told_when_another_file_remains_to_attach()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $page = Page::query()->create([
            'title' => 'De Planeten', 'slug' => 'de-planeten', 'topic_id' => $topic->id,
        ]);

        Storage::fake('local');
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        // Never attached to any page, so it is the one thing the "choose a
        // file" dialog would find.
        MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'ongebruikt.pdf',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('attachableFilesAvailable', true)
            );
    }

    public function test_the_topic_and_page_forms_receive_the_password_list()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        AccessPassword::createWithPassword('5 VWO', 'geheim');

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.topics.create'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));

        $this->get(route('admin.topics.edit', $topic))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));

        $this->get(route('admin.pages.create'))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia->has('passwords', 1));
    }

    public function test_a_password_can_be_applied_to_a_topic_through_the_form()
    {
        $topic = Topic::query()->create(['title' => 'Natuurkunde', 'slug' => 'natuurkunde']);
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');

        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
                'sort_order' => 0,
                'is_hidden' => false,
                'access_password_id' => $password->id,
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertSame($password->id, $topic->fresh()->access_password_id);
    }

    public function test_choosing_no_password_clears_it_rather_than_failing()
    {
        $password = AccessPassword::createWithPassword('5 VWO', 'geheim');
        $topic = Topic::query()->create([
            'title' => 'Natuurkunde', 'slug' => 'natuurkunde',
            'access_password_id' => $password->id,
        ]);

        // The select submits an empty string for "geen wachtwoord"; that has
        // to become a real null, not an empty string cast to 0.
        $this->actingAs(User::factory()->create())
            ->put(route('admin.topics.update', $topic), [
                'title' => 'Natuurkunde',
                'slug' => 'natuurkunde',
                'parent_id' => null,
                'sort_order' => 0,
                'is_hidden' => false,
                'access_password_id' => '',
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertNull($topic->fresh()->access_password_id);
    }
}
