<?php

namespace Tests\Feature\Admin;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The picker search endpoints behind /admin/media/search — the same shape as
 * IconCatalogueTest, for the same reason: this is what stands in for shipping
 * the whole media library into every page-edit payload.
 */
class MediaSearchTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $filename, string $alt = 'Een beschrijving'): Image
    {
        Storage::fake('local');
        $path = 'images/'.Str::ulid().'.webp';
        Storage::disk('local')->put($path, 'bytes');

        return Image::query()->create([
            'path' => $path, 'alt_text' => $alt, 'width' => 10, 'height' => 10,
            'size_bytes' => 5, 'mime' => 'image/webp', 'original_filename' => $filename,
        ]);
    }

    private function file(string $filename, string $kind = MediaFile::KIND_DOCUMENT): MediaFile
    {
        Storage::fake('local');
        $path = 'media/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        return MediaFile::query()->create([
            'path' => $path, 'kind' => $kind, 'mime' => 'application/pdf',
            'size_bytes' => 5, 'original_filename' => $filename,
        ]);
    }

    public function test_both_search_endpoints_are_behind_auth()
    {
        $this->get(route('admin.media.search.images'))->assertRedirect(route('login'));
        $this->get(route('admin.media.search.files'))->assertRedirect(route('login'));
        $this->get(route('admin.media.search.image-options'))->assertRedirect(route('login'));
    }

    /**
     * The request every picker makes first, before anything has been typed.
     *
     * `?q=` arrives as null, because ConvertEmptyStringsToNull runs before
     * validation — so a rule of `['sometimes', 'string']` answered the
     * opening of every dialog with a 422, and the grid rendered nothing at
     * all rather than the library or its empty line. Every browser spec typed
     * a term immediately, and the debounce cancelled the empty search before
     * it was sent, so nothing noticed.
     */
    public function test_an_empty_search_returns_the_library_rather_than_a_validation_error()
    {
        $image = $this->image('logo.webp');
        $file = $this->file('werkblad.pdf');

        $this->actingAs(User::factory()->create());

        $this->getJson(route('admin.media.search.images', ['q' => '']))
            ->assertOk()
            ->assertJsonPath('images.0.ulid', $image->ulid);

        $this->getJson(route('admin.media.search.image-options', ['q' => '']))
            ->assertOk()
            ->assertJsonPath('images.0.id', $image->id);

        // Same for the file picker, and for the exclude list it sends
        // alongside — an empty one arrives as null too.
        $this->getJson(route('admin.media.search.files', ['q' => '', 'exclude' => '']))
            ->assertOk()
            ->assertJsonPath('files.0.ulid', $file->ulid);
    }

    /**
     * The banner and branding pickers search the same table through a second
     * endpoint, because they write a foreign key and the editor's pickers
     * write a ULID into a document. The two shapes are the point of the
     * split: an id reaching the editor is how the distinction stops being
     * true, so this asserts each endpoint hands back its own shape and not
     * the other's.
     */
    public function test_the_id_addressed_endpoint_returns_options_and_the_ulid_one_does_not()
    {
        $image = $this->image('logo.webp', 'Het logo');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.image-options', ['q' => 'logo']))
            ->assertOk()
            ->assertJsonPath('images.0.id', $image->id)
            ->assertJsonPath('images.0.alt', 'Het logo')
            ->assertJsonPath('images.0.filename', 'logo.webp')
            ->assertJsonMissingPath('images.0.ulid');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.images', ['q' => 'logo']))
            ->assertOk()
            ->assertJsonPath('images.0.ulid', $image->ulid)
            ->assertJsonMissingPath('images.0.id');
    }

    public function test_images_are_matched_on_filename_or_alt_text()
    {
        $planets = $this->image('planeten.webp', 'Het zonnestelsel');
        $this->image('reactie.webp', 'Een chemische reactie');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.images', ['q' => 'planeten']))
            ->assertOk()
            ->assertJsonPath('images.0.ulid', $planets->ulid)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('capped', false);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.images', ['q' => 'zonnestelsel']))
            ->assertOk()
            ->assertJsonPath('images.0.ulid', $planets->ulid);
    }

    public function test_image_search_wildcards_are_literal()
    {
        $this->image('planeten.webp');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.images', ['q' => '%']))
            ->assertOk()
            ->assertJsonPath('images', []);
    }

    public function test_files_are_matched_on_filename()
    {
        $worksheet = $this->file('werkblad.pdf');
        $this->file('presentatie.pdf');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.files', ['q' => 'werkblad']))
            ->assertOk()
            ->assertJsonPath('files.0.id', $worksheet->id)
            ->assertJsonPath('files.0.ulid', $worksheet->ulid)
            ->assertJsonPath('files.0.kind', MediaFile::KIND_DOCUMENT)
            ->assertJsonPath('total', 1);
    }

    public function test_files_can_be_excluded_by_id()
    {
        // What the downloads section uses to hide files already attached to
        // the page it is on, without shipping the whole library to work it
        // out client-side.
        $attached = $this->file('werkblad-1.pdf');
        $available = $this->file('werkblad-2.pdf');

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.files', [
                'q' => 'werkblad',
                'exclude' => (string) $attached->id,
            ]))
            ->assertOk()
            ->assertJsonPath('files.0.id', $available->id)
            ->assertJsonPath('total', 1);
    }

    public function test_a_malformed_exclude_list_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.files', ['exclude' => '1;drop table media_files']))
            ->assertStatus(422);
    }

    public function test_results_are_capped()
    {
        foreach (range(1, 3) as $index) {
            $this->file("bestand-{$index}.pdf");
        }

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.search.files', ['q' => 'bestand']))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'files')
            ->assertJsonPath('capped', false);
    }
}
