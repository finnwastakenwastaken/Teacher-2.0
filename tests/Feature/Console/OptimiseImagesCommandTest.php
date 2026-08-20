<?php

namespace Tests\Feature\Console;

use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Tests\TestCase;

/**
 * Conversion happens on upload, so this command only matters for what was
 * already in the library before that existed — which, for a teacher, is a
 * handful of photos straight off a phone.
 */
class OptimiseImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function storeImage(string $extension = 'png', int $width = 1600, int $height = 1200): Image
    {
        $source = new Imagick;
        $source->newPseudoImage($width, $height, 'plasma:fractal');
        $source->setImageFormat($extension);

        $blob = $source->getImageBlob();
        $path = 'images/2026/08/'.Str::ulid().'.'.$extension;

        Storage::disk('local')->put($path, $blob);

        return Image::query()->create([
            'ulid' => (string) Str::ulid(),
            'path' => $path,
            'alt_text' => 'Een foto',
            'width' => $width,
            'height' => $height,
            'size_bytes' => strlen($blob),
            'mime' => 'image/'.$extension,
            'original_filename' => 'IMG_0629.'.$extension,
        ]);
    }

    public function test_a_dry_run_reports_but_changes_nothing()
    {
        config(['media.images.max_bytes' => 50 * 1024]);

        $image = $this->storeImage();

        $this->artisan('media:optimise')
            ->expectsOutputToContain('Er is nog niets veranderd')
            ->assertSuccessful();

        $fresh = $image->fresh();

        $this->assertSame($image->path, $fresh->path);
        $this->assertSame($image->size_bytes, $fresh->size_bytes);
        $this->assertSame('image/png', $fresh->mime);
        Storage::disk('local')->assertExists($image->path);
    }

    public function test_force_rewrites_the_file_and_keeps_the_ulid()
    {
        config(['media.images.max_bytes' => 50 * 1024]);

        $image = $this->storeImage();

        $this->artisan('media:optimise --force')->assertSuccessful();

        $fresh = $image->fresh();

        // The ULID is what every embed, download and banner points at. If it
        // moved, this command would break the site it was meant to speed up.
        $this->assertSame($image->ulid, $fresh->ulid);
        $this->assertSame('image/webp', $fresh->mime);
        $this->assertStringEndsWith('.webp', $fresh->path);
        $this->assertSame('IMG_0629.webp', $fresh->original_filename);
        $this->assertLessThan($image->size_bytes, $fresh->size_bytes);

        Storage::disk('local')->assertExists($fresh->path);
        Storage::disk('local')->assertMissing($image->path);
        $this->assertSame(
            $fresh->size_bytes,
            strlen(Storage::disk('local')->get($fresh->path))
        );
    }

    public function test_images_under_the_ceiling_are_left_alone_unless_all_is_given()
    {
        config(['media.images.max_bytes' => 50 * 1024 * 1024]);

        $image = $this->storeImage();

        $this->artisan('media:optimise --force')
            ->expectsOutputToContain('Geen afbeeldingen groter dan')
            ->assertSuccessful();

        $this->assertSame('image/png', $image->fresh()->mime);

        $this->artisan('media:optimise --all --force')->assertSuccessful();

        $this->assertSame('image/webp', $image->fresh()->mime);
    }

    public function test_a_missing_file_is_reported_rather_than_fatal()
    {
        config(['media.images.max_bytes' => 1]);

        $image = $this->storeImage();
        Storage::disk('local')->delete($image->path);

        $this->artisan('media:optimise --force')
            ->expectsOutputToContain('Bestand ontbreekt')
            ->assertSuccessful();

        $this->assertSame('image/png', $image->fresh()->mime);
    }
}
