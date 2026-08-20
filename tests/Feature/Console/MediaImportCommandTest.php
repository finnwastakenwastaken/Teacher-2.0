<?php

namespace Tests\Feature\Console;

use App\Models\Image;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `php artisan media:import` is the documented escape hatch for material too
 * large to send through the browser at all — Cloudflare rejects bodies over
 * 100 MB and, while chunked upload works around that, a multi-gigabyte
 * recording is happier being copied onto the server with scp and registered
 * from there.
 */
class MediaImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $importPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->importPath = storage_path('framework/testing/media-import');
        File::ensureDirectoryExists($this->importPath);
        File::cleanDirectory($this->importPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->importPath);

        parent::tearDown();
    }

    private function drop(string $filename, string $contents): string
    {
        $path = $this->importPath.'/'.$filename;
        File::put($path, $contents);

        return $path;
    }

    public function test_it_reports_a_missing_directory_rather_than_crashing()
    {
        $this->artisan('media:import', ['--path' => '/no/such/directory'])
            ->assertExitCode(1);
    }

    public function test_it_is_a_no_op_on_an_empty_directory()
    {
        $this->artisan('media:import', ['--path' => $this->importPath])
            ->assertExitCode(0);

        $this->assertSame(0, MediaFile::query()->count());
    }

    public function test_it_registers_a_document()
    {
        $this->drop('werkblad.pdf', '%PDF-1.4 inhoud van het werkblad');

        $this->artisan('media:import', ['--path' => $this->importPath])
            ->assertExitCode(0);

        $file = MediaFile::query()->sole();
        $this->assertSame(MediaFile::KIND_DOCUMENT, $file->kind);
        $this->assertSame('werkblad.pdf', $file->original_filename);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_a_video_is_recognised_from_its_extension_when_sniffing_is_inconclusive()
    {
        // A stub file has no recognisable container header, so content
        // sniffing lands on application/octet-stream. Falling back to the
        // owner's own filename is the right call here — they are the only
        // person who can put a file in this directory.
        $this->drop('college.mp4', str_repeat('x', 64));

        $this->artisan('media:import', ['--path' => $this->importPath])
            ->assertExitCode(0);

        $this->assertSame(MediaFile::KIND_VIDEO, MediaFile::query()->sole()->kind);
    }

    public function test_an_image_needs_alt_text_and_the_alt_option_supplies_it()
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->drop('diagram.png', $png);

        // Without --alt the image is refused, because an image with no alt
        // text is invisible to a screen reader on every page that uses it.
        $this->artisan('media:import', ['--path' => $this->importPath])
            ->assertExitCode(1);

        $this->assertSame(0, Image::query()->count());

        $this->artisan('media:import', [
            '--path' => $this->importPath,
            '--alt' => 'Een schema van een schakeling',
        ])->assertExitCode(0);

        $image = Image::query()->sole();
        $this->assertSame('Een schema van een schakeling', $image->alt_text);
        $this->assertSame(1, $image->width);
    }

    public function test_the_source_file_is_left_in_place_by_default()
    {
        $source = $this->drop('werkblad.pdf', '%PDF-1.4 x');

        $this->artisan('media:import', ['--path' => $this->importPath])
            ->assertExitCode(0);

        // The import directory is a root-owned bind mount in production,
        // where PHP can often read a file but not unlink it. Copying by
        // default means an ownership quirk cannot fail the whole import.
        $this->assertFileExists($source);
    }

    public function test_prune_removes_the_source_after_a_successful_import()
    {
        $source = $this->drop('werkblad.pdf', '%PDF-1.4 x');

        $this->artisan('media:import', ['--path' => $this->importPath, '--prune' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($source);
        $this->assertSame(1, MediaFile::query()->count());
    }
}
