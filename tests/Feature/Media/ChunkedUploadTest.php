<?php

namespace Tests\Feature\Media;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chunked upload is not an optimisation — Cloudflare's Free and Pro plans
 * reject any request body over 100 MB, so a browser cannot send a lecture
 * video in one piece through the tunnel at all.
 *
 * The chunk size is shrunk to a few bytes here so the mechanics can be tested
 * without moving megabytes around.
 */
class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG, so dimension detection has something to read. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['media.chunk_bytes' => 8]);
    }

    private function begin(User $admin, string $filename, string $contents): array
    {
        return $this->actingAs($admin)->postJson(route('admin.uploads.store'), [
            'filename' => $filename,
            'size' => strlen($contents),
        ])->assertCreated()->json();
    }

    private function sendChunks(User $admin, string $ulid, string $contents, int $chunkBytes): void
    {
        foreach (str_split($contents, $chunkBytes) as $index => $piece) {
            $this->actingAs($admin)
                ->post(route('admin.uploads.chunk', ['upload' => $ulid, 'index' => $index]), [
                    'chunk' => UploadedFile::fake()->createWithContent('chunk', $piece),
                ])
                ->assertOk();
        }
    }

    public function test_guests_cannot_start_an_upload()
    {
        $this->postJson(route('admin.uploads.store'), ['filename' => 'x.pdf', 'size' => 10])
            ->assertUnauthorized();
    }

    public function test_a_document_uploads_across_several_chunks()
    {
        $admin = User::factory()->create();
        $contents = '%PDF-1.4 werkblad met een aantal bytes erin';

        $session = $this->begin($admin, 'werkblad.pdf', $contents);

        $this->assertSame(8, $session['chunkBytes']);
        $this->assertSame((int) ceil(strlen($contents) / 8), $session['totalChunks']);

        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))
            ->assertCreated();

        $file = MediaFile::query()->sole();
        $this->assertSame(MediaFile::KIND_DOCUMENT, $file->kind);
        $this->assertSame('werkblad.pdf', $file->original_filename);
        $this->assertSame(strlen($contents), $file->size_bytes);
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($contents, Storage::disk('local')->get($file->path));
    }

    public function test_completing_describes_the_new_file_for_the_page_editor()
    {
        // The type comes from sniffing the bytes, so only the server can
        // tell the editor what the upload turned out to be.
        $admin = User::factory()->create();
        $contents = '%PDF-1.4 werkblad';

        $session = $this->begin($admin, 'werkblad.pdf', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $body = $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))
            ->assertCreated()
            ->json();

        $file = MediaFile::query()->sole();

        $this->assertSame('file', $body['type']);
        $this->assertSame($file->id, $body['id']);
        $this->assertSame($file->ulid, $body['ulid']);
        $this->assertSame(MediaFile::KIND_DOCUMENT, $body['kind']);
        $this->assertSame('werkblad.pdf', $body['original_filename']);
        $this->assertSame(strlen($contents), $body['size_bytes']);
        $this->assertSame(route('media.show', $file), $body['url']);
    }

    public function test_completing_describes_a_new_image_as_an_image()
    {
        $admin = User::factory()->create();
        $contents = base64_decode(self::PNG);

        $session = $this->begin($admin, 'grafiek.png', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $body = $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]), [
                'alt_text' => 'Een grafiek',
            ])
            ->assertCreated()
            ->json();

        $image = Image::query()->sole();

        $this->assertSame('image', $body['type']);
        $this->assertSame($image->id, $body['id']);
        $this->assertSame('Een grafiek', $body['alt_text']);
        $this->assertSame(route('images.show', $image), $body['url']);
        // A file embed cannot render an image, so the client needs to be able
        // to tell them apart without guessing from the filename.
        $this->assertArrayNotHasKey('kind', $body);
    }

    public function test_the_stored_path_is_built_from_a_ulid_not_the_original_filename()
    {
        $admin = User::factory()->create();
        $contents = '%PDF-1.4 x';

        $session = $this->begin($admin, 'Rapport eindexamen 2026.pdf', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);
        $this->actingAs($admin)->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))->assertCreated();

        $file = MediaFile::query()->sole();

        $this->assertStringNotContainsString('Rapport', $file->path);
        $this->assertStringNotContainsString(' ', $file->path);
        $this->assertStringEndsWith('.pdf', $file->path);
        // The owner's filename survives as the download name.
        $this->assertSame('Rapport eindexamen 2026.pdf', $file->original_filename);
        $this->assertStringContainsString($file->ulid, $file->path);
    }

    /**
     * The row's ULID and the one in its path used to differ for images only —
     * the path used one, createImage() minted another — leaving a file that
     * couldn't be traced back to its row without a LIKE query.
     */
    public function test_an_image_row_and_its_file_carry_the_same_ulid()
    {
        $admin = User::factory()->create();
        $contents = base64_decode(self::PNG);

        $session = $this->begin($admin, 'foto.png', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);
        $this->actingAs($admin)->postJson(
            route('admin.uploads.complete', ['upload' => $session['ulid']]),
            ['alt_text' => 'Een foto'],
        )->assertCreated();

        $image = Image::query()->sole();

        $this->assertStringContainsString($image->ulid, $image->path);
    }

    public function test_an_image_requires_alt_text()
    {
        $admin = User::factory()->create();
        $contents = base64_decode(self::PNG);

        $session = $this->begin($admin, 'diagram.png', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))
            ->assertStatus(422);

        $this->assertSame(0, Image::query()->count());
    }

    public function test_an_image_with_alt_text_records_its_dimensions()
    {
        $admin = User::factory()->create();
        $contents = base64_decode(self::PNG);

        $session = $this->begin($admin, 'diagram.png', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]), [
                'alt_text' => 'Een schema van een elektrische schakeling',
            ])
            ->assertCreated();

        $image = Image::query()->sole();
        $this->assertSame(1, $image->width);
        $this->assertSame(1, $image->height);
        $this->assertSame('image/png', $image->mime);
    }

    public function test_a_chunk_of_the_wrong_size_is_rejected()
    {
        $admin = User::factory()->create();
        $contents = 'abcdefghijklmnop';

        $session = $this->begin($admin, 'notes.txt', $contents);

        $this->actingAs($admin)
            ->post(route('admin.uploads.chunk', ['upload' => $session['ulid'], 'index' => 0]), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', 'short'),
            ])
            ->assertStatus(422);
    }

    public function test_a_chunk_index_beyond_the_declared_count_is_rejected()
    {
        $admin = User::factory()->create();
        $contents = 'abcdefghijklmnop';

        $session = $this->begin($admin, 'notes.txt', $contents);

        $this->actingAs($admin)
            ->post(route('admin.uploads.chunk', ['upload' => $session['ulid'], 'index' => 99]), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abcdefgh'),
            ])
            ->assertStatus(422);
    }

    public function test_completing_an_upload_with_a_missing_chunk_is_rejected()
    {
        $admin = User::factory()->create();
        $contents = 'abcdefghijklmnop';

        $session = $this->begin($admin, 'notes.txt', $contents);

        // Only the first of two chunks.
        $this->actingAs($admin)
            ->post(route('admin.uploads.chunk', ['upload' => $session['ulid'], 'index' => 0]), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abcdefgh'),
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))
            ->assertStatus(422);

        $this->assertSame(0, MediaFile::query()->count());
    }

    public function test_a_file_larger_than_the_maximum_is_refused_up_front()
    {
        $admin = User::factory()->create();
        config(['media.max_bytes' => 100]);

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.store'), ['filename' => 'huge.mp4', 'size' => 500])
            ->assertStatus(422);
    }

    public function test_aborting_an_upload_removes_its_chunks()
    {
        $admin = User::factory()->create();
        $contents = 'abcdefghijklmnop';

        $session = $this->begin($admin, 'notes.txt', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $upload = MediaUpload::query()->where('ulid', $session['ulid'])->sole();
        Storage::disk('local')->assertExists($upload->chunkDirectory().'/0');

        $this->actingAs($admin)
            ->deleteJson(route('admin.uploads.destroy', ['upload' => $session['ulid']]))
            ->assertOk();

        Storage::disk('local')->assertMissing($upload->chunkDirectory().'/0');
        $this->assertSame(0, MediaUpload::query()->count());
    }

    public function test_completing_an_upload_cleans_up_its_chunks()
    {
        $admin = User::factory()->create();
        $contents = '%PDF-1.4 x';

        $session = $this->begin($admin, 'werkblad.pdf', $contents);
        $upload = MediaUpload::query()->where('ulid', $session['ulid'])->sole();
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $this->actingAs($admin)
            ->postJson(route('admin.uploads.complete', ['upload' => $session['ulid']]))
            ->assertCreated();

        Storage::disk('local')->assertMissing($upload->chunkDirectory());
        $this->assertSame(0, MediaUpload::query()->count());
    }

    public function test_an_expired_upload_is_pruned_with_its_chunks()
    {
        $admin = User::factory()->create();
        $contents = 'abcdefghijklmnop';

        $session = $this->begin($admin, 'notes.txt', $contents);
        $this->sendChunks($admin, $session['ulid'], $contents, 8);

        $upload = MediaUpload::query()->where('ulid', $session['ulid'])->sole();
        $upload->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('media:prune-uploads')->assertExitCode(0);

        Storage::disk('local')->assertMissing($upload->chunkDirectory());
        $this->assertSame(0, MediaUpload::query()->count());
    }
}
