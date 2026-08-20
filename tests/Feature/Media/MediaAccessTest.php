<?php

namespace Tests\Feature\Media;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "no side door" guarantee (the technical reference): uploaded bytes leave
 * this application through exactly one authorised controller, and nowhere
 * else.
 *
 * MEDIA_X_ACCEL is forced off for the suite (see tests/bootstrap.php) so the
 * controller streams files itself and these tests can assert on real response
 * bodies. Authorisation happens before that branch, so what is proved here
 * holds for the nginx path too — and the header itself is asserted separately
 * below.
 */
class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeImage(string $contents = 'image-bytes', string $mime = 'image/png'): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.png';
        Storage::disk('local')->put($path, $contents);

        return Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een diagram van het zonnestelsel',
            'size_bytes' => strlen($contents),
            'mime' => $mime,
            'original_filename' => 'zonnestelsel.png',
        ]);
    }

    private function makeFile(string $kind = MediaFile::KIND_DOCUMENT, string $mime = 'application/pdf'): MediaFile
    {
        $contents = 'file-bytes';
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, $contents);

        return MediaFile::query()->create([
            'path' => $path,
            'kind' => $kind,
            'mime' => $mime,
            'size_bytes' => strlen($contents),
            'original_filename' => 'werkblad.pdf',
        ]);
    }

    public function test_an_anonymous_visitor_cannot_fetch_an_image()
    {
        $image = $this->makeImage();

        $this->get(route('images.show', $image))->assertForbidden();
    }

    public function test_an_anonymous_visitor_cannot_fetch_a_media_file()
    {
        $file = $this->makeFile();

        $this->get(route('media.show', $file))->assertForbidden();
    }

    public function test_the_admin_can_fetch_an_image()
    {
        $image = $this->makeImage('the-real-bytes');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image));

        $response->assertOk();
        $this->assertSame('the-real-bytes', $response->streamedContent());
    }

    public function test_media_urls_are_addressed_by_ulid_not_by_id()
    {
        $image = $this->makeImage();

        // Sequential ids would let anyone enumerate the whole library and
        // learn how much material exists, even while every fetch is refused.
        $this->assertStringContainsString($image->ulid, route('images.show', $image));
        $this->assertStringNotContainsString('/'.$image->id, route('images.show', $image));
    }

    public function test_an_unknown_identifier_is_a_404_not_a_403()
    {
        $this->actingAs(User::factory()->create())
            ->get('/images/'.Str::ulid())
            ->assertNotFound();
    }

    public function test_a_row_pointing_outside_the_library_directories_is_refused()
    {
        // The nginx location is aliased straight onto the private disk root,
        // so a path that escaped the two library directories — a chunk
        // staging file, a traversal sequence — would otherwise be handed
        // over verbatim.
        Storage::disk('local')->put('chunks/abc/0', 'partial-upload-bytes');

        $image = Image::query()->create([
            'path' => 'chunks/abc/0',
            'alt_text' => 'x',
            'size_bytes' => 10,
            'mime' => 'image/png',
            'original_filename' => 'x.png',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image))
            ->assertNotFound();
    }

    public function test_a_traversal_path_is_refused()
    {
        $image = Image::query()->create([
            'path' => 'images/../../../.env',
            'alt_text' => 'x',
            'size_bytes' => 10,
            'mime' => 'image/png',
            'original_filename' => 'x.png',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image))
            ->assertNotFound();
    }

    public function test_the_x_accel_redirect_header_points_into_the_internal_nginx_location()
    {
        config(['media.x_accel' => true]);

        $image = $this->makeImage();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image));

        $response->assertOk();
        $response->assertHeader('X-Accel-Redirect', '/__media/'.$image->path);
        // nginx replaces the body; PHP must not also send it.
        $this->assertSame('', $response->getContent());
        // Nor Accept-Ranges — nginx sets that itself when it serves the
        // file, and sending it here produced a duplicated header.
        $response->assertHeaderMissing('Accept-Ranges');
    }

    public function test_gated_media_is_never_cached_by_a_shared_cache()
    {
        $image = $this->makeImage();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image));

        // Access depends on who is asking, and will depend on an unlock
        // cookie once passwords land. A CDN handing a cached copy to the
        // next visitor would defeat the entire gate.
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_a_document_is_served_as_an_attachment_and_a_video_inline()
    {
        $admin = User::factory()->create();

        $document = $this->makeFile(MediaFile::KIND_DOCUMENT, 'application/pdf');
        $video = $this->makeFile(MediaFile::KIND_VIDEO, 'video/mp4');

        $this->actingAs($admin)->get(route('media.show', $document))
            ->assertHeaderMissing('X-Accel-Redirect')
            ->assertDownload('werkblad.pdf');

        $response = $this->actingAs($admin)->get(route('media.show', $video));
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_an_svg_is_forced_to_download_and_sandboxed()
    {
        $image = $this->makeImage('<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('images.show', $image));

        // SVG is XML and can carry <script>. Navigating straight to it would
        // otherwise render it as a document in this origin.
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_private_disk_creates_files_nginx_can_read()
    {
        // Two containers share this volume: PHP-FPM writes as www-data,
        // nginx reads as its own user. Flysystem's private default creates
        // directories 0700, which nginx cannot even traverse — every gated
        // URL then 403s in production while this suite stays green, because
        // tests run with MEDIA_X_ACCEL=false and read the file as the same
        // user that wrote it.
        //
        // Storage::fake() does not inherit the real disk's config, so this
        // asserts the configuration directly rather than a created file.
        $dir = config('filesystems.disks.local.permissions.dir.private');
        $file = config('filesystems.disks.local.permissions.file.private');

        $this->assertNotNull($dir, 'The local disk must set explicit permissions.');
        $this->assertSame(05, $dir & 05, 'Media directories must stay traversable and readable by the nginx user.');
        $this->assertSame(04, $file & 04, 'Media files must stay readable by the nginx user.');
    }

    public function test_laravels_own_storage_routes_do_not_exist()
    {
        // `serve => true` on the private disk registers GET and PUT routes at
        // /storage/{path} that read and write it directly, gated only by a
        // signed URL — a second way to reach media that skips MediaAccess
        // entirely, and one that Storage::temporaryUrl() would happily mint
        // shareable links for. config/filesystems.php turns it off; this is
        // the guard that keeps it off.
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();

        $this->assertNotContains('storage.local', $names);
        $this->assertNotContains('storage.local.upload', $names);
    }
}
