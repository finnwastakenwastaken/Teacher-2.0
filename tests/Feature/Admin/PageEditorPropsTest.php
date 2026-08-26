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

        // Embedded via the body, separate from the download (page_media_references
        // vs page_downloads) — proves mediaLibrary reflects only the body, not downloads.
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
                // Only the embedded file (from page_media_references), not the whole
                // table or the download below — pickers ask MediaSearchController for the rest.
                ->has('mediaLibrary.files', 1)
                ->where('mediaLibrary.files.0.id', $embedded->id)
                ->where('mediaLibrary.files.0.ulid', $embedded->ulid)
                ->has('passwords', 1)
                ->has('educationLevels', 1)
                ->has('downloads', 1)
                ->where('downloads.0.label', 'Werkblad')
                ->where('downloads.0.educationLevelIds', [$level->id])
                ->where('downloads.0.source', 'file')
                ->where('downloads.0.mediaId', $file->id)
                ->where('downloads.0.kind', MediaFile::KIND_DOCUMENT)
                // No thumbnail: this attachment offers a document. An offered
                // image sends one, so the admin list can tell three posters apart.
                ->where('downloads.0.previewUrl', null)
                // Not attached as a download yet, so still offerable — the boolean
                // the downloads section checks instead of receiving the whole library.
                ->where('attachableMediaAvailable', true)
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

        // None of these are embedded or attached as downloads — the payload must not
        // grow with library size regardless.
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

        // Reconstructs the old mediaLibrary shape (whole images/media_files tables)
        // to compare against, rather than reverting the controller.
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

        // This is the entire edit screen, not just mediaLibrary, and still
        // smaller than the old mediaLibrary block alone.
        $this->assertLessThan($oldMediaLibraryBytes, $newBytes);

        // Banner picker now carries only what it points at (nothing here); asserted
        // separately since a byte-count check alone could still pass if it regressed.
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
                // Two, not twenty — never more than three, the number of settings
                // that hold an image id.
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

        $this->actingAs(User::factory()->create());

        // Nothing in either library yet.
        $this->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('attachableMediaAvailable', false)
            );

        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');
        // Never attached to any page, so it is the one thing the "choose from
        // the library" dialog would find.
        $file = MediaFile::query()->create([
            'path' => $path, 'kind' => MediaFile::KIND_DOCUMENT, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => 'ongebruikt.pdf',
        ]);

        $this->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('attachableMediaAvailable', true)
            );

        // Attaching the only document does not exhaust the dialog any more:
        // an image is offerable too, and a page whose every document is
        // already attached can still be handed a poster.
        $page->downloads()->create(['media_file_id' => $file->id]);

        $this->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('attachableMediaAvailable', false)
            );

        Storage::disk('local')->put('images/poster.webp', 'bytes');
        Image::query()->create([
            'path' => 'images/poster.webp', 'alt_text' => 'Poster',
            'size_bytes' => 5, 'mime' => 'image/webp',
            'original_filename' => 'poster.webp',
        ]);

        $this->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('attachableMediaAvailable', true)
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
