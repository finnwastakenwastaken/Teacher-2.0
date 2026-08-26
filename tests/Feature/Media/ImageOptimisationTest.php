<?php

namespace Tests\Feature\Media;

use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\TestCase;

/**
 * Every raster image is re-encoded to WebP on the way in.
 *
 * The HEIC half of this is not a size optimisation: no browser can display
 * HEIC, so a photo straight off an iPhone stored as it arrived would render
 * as nothing on the public page.
 */
class ImageOptimisationTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['media.chunk_bytes' => 1024 * 1024]);
    }

    private function upload(User $admin, string $filename, string $contents, ?string $altText = 'Alt'): array
    {
        $session = $this->actingAs($admin)->postJson(route('admin.uploads.store'), [
            'filename' => $filename,
            'size' => strlen($contents),
        ])->assertCreated()->json();

        foreach (str_split($contents, $session['chunkBytes']) as $index => $piece) {
            $this->actingAs($admin)
                ->post(route('admin.uploads.chunk', ['upload' => $session['ulid'], 'index' => $index]), [
                    'chunk' => UploadedFile::fake()->createWithContent('chunk', $piece),
                ])
                ->assertOk();
        }

        return $this->actingAs($admin)
            ->postJson(
                route('admin.uploads.complete', ['upload' => $session['ulid']]),
                $altText === null ? [] : ['alt_text' => $altText],
            )
            ->assertCreated()
            ->json();
    }

    /** A JPEG of random noise, which does not compress away to nothing. */
    private function noisyJpeg(int $width, int $height): string
    {
        $image = new Imagick;
        $image->newPseudoImage($width, $height, 'plasma:fractal');
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(100);

        return $image->getImageBlob();
    }

    public function test_the_image_stack_can_read_heic_and_write_webp()
    {
        // A tripwire: both formats come from separate Alpine packages
        // (imagemagick-heic, imagemagick-webp); dropping either silently
        // turns every iPhone photo into an upload the owner cannot use.
        $formats = Imagick::queryFormats();

        $this->assertContains('HEIC', $formats);
        $this->assertContains('WEBP', $formats);
    }

    public function test_imagemagick_refuses_the_coders_that_execute_instructions()
    {
        // resolveMime() can fall back to filename, so a ".png" can still
        // reach readImage() recognised as something else. These coders turn
        // decoding into executing instructions; imagemagick-policy.xml
        // disables them, and only this test would notice that regressing.
        foreach (['MSL', 'MVG', 'HTTPS', 'EPHEMERAL'] as $coder) {
            $blocked = false;

            try {
                (new Imagick)->readImage($coder.':/etc/passwd');
            } catch (\Throwable) {
                $blocked = true;
            }

            $this->assertTrue($blocked, "The {$coder} coder should be disabled.");
        }
    }

    public function test_a_heic_photo_is_converted_to_webp()
    {
        $admin = User::factory()->create();
        $heic = file_get_contents(base_path('tests/Fixtures/portret.heic'));

        $body = $this->upload($admin, 'vakantie.HEIC', $heic, 'Een foto van de vakantie');

        $image = Image::query()->sole();

        $this->assertSame('image', $body['type']);
        $this->assertSame('image/webp', $image->mime);
        $this->assertStringEndsWith('.webp', $image->path);
        // The filename is a display label and the name a download saves
        // under, so leaving it claiming to be a HEIC would hand the owner a
        // file their own computer opens wrongly.
        $this->assertSame('vakantie.webp', $image->original_filename);
        $this->assertSame(320, $image->width);
        $this->assertSame(240, $image->height);

        $stored = Storage::disk('local')->get($image->path);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($stored));
    }

    public function test_a_png_is_re_encoded_to_webp()
    {
        $admin = User::factory()->create();

        $source = new Imagick;
        $source->newPseudoImage(600, 400, 'plasma:fractal');
        $source->setImageFormat('png');

        $this->upload($admin, 'grafiek.png', $source->getImageBlob(), 'Een grafiek');

        $image = Image::query()->sole();

        $this->assertSame('image/webp', $image->mime);
        $this->assertSame('grafiek.webp', $image->original_filename);
    }

    public function test_an_image_over_the_ceiling_is_compressed_under_it()
    {
        config(['media.images.max_bytes' => 60 * 1024]);

        $admin = User::factory()->create();
        $source = $this->noisyJpeg(1800, 1400);

        $this->assertGreaterThan(60 * 1024, strlen($source));

        $this->upload($admin, 'foto.jpg', $source, 'Een foto');

        $image = Image::query()->sole();

        $this->assertLessThanOrEqual(60 * 1024, $image->size_bytes);
        $this->assertSame($image->size_bytes, strlen(Storage::disk('local')->get($image->path)));
    }

    public function test_an_oversized_image_is_scaled_down_to_the_maximum_dimension()
    {
        config(['media.images.max_dimension' => 400]);

        $admin = User::factory()->create();

        $this->upload($admin, 'breed.jpg', $this->noisyJpeg(1200, 600), 'Breed');

        $image = Image::query()->sole();

        $this->assertSame(400, $image->width);
        // Aspect ratio is kept: 1200x600 halves twice to 400x200.
        $this->assertSame(200, $image->height);
    }

    public function test_a_small_image_is_never_enlarged()
    {
        $admin = User::factory()->create();

        $this->upload($admin, 'klein.png', base64_decode(self::PNG_1X1), 'Klein');

        $image = Image::query()->sole();

        $this->assertSame(1, $image->width);
        $this->assertSame(1, $image->height);
    }

    public function test_an_svg_is_left_exactly_as_it_is()
    {
        // SVG is vector. Rasterising it would be a downgrade, and it is
        // already smaller than anything a re-encode could produce.
        $admin = User::factory()->create();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>';

        $this->upload($admin, 'logo.svg', $svg, 'Logo');

        $image = Image::query()->sole();

        $this->assertSame('image/svg+xml', $image->mime);
        $this->assertSame('logo.svg', $image->original_filename);
        $this->assertSame($svg, Storage::disk('local')->get($image->path));
    }

    public function test_an_animated_gif_keeps_its_frames()
    {
        // Flattening an animation to a single frame destroys it silently,
        // which is the worst way for a conversion to fail.
        $admin = User::factory()->create();

        $animation = new Imagick;

        foreach (['red', 'blue', 'green'] as $colour) {
            $frame = new Imagick;
            $frame->newImage(20, 20, $colour);
            $frame->setImageFormat('gif');
            $frame->setImageDelay(20);
            $animation->addImage($frame);
        }

        $animation->setImageFormat('gif');

        $this->upload($admin, 'animatie.gif', $animation->getImagesBlob(), 'Animatie');

        $image = Image::query()->sole();

        $this->assertSame('image/gif', $image->mime);

        $stored = new Imagick;
        $stored->readImageBlob(Storage::disk('local')->get($image->path));

        $this->assertSame(3, $stored->getNumberImages());
    }

    public function test_photo_metadata_does_not_survive_the_conversion()
    {
        // A phone photo carries GPS coordinates. This site records nothing
        // about anybody by design; publishing the coordinates of the owner's
        // home inside an image on a public page would make a mockery of that.
        $admin = User::factory()->create();

        $source = new Imagick;
        $source->newPseudoImage(200, 150, 'plasma:fractal');
        $source->setImageFormat('jpeg');
        $source->setImageProperty('exif:GPSLatitude', '52/1 22/1 12/1');
        $source->setImageProperty('exif:Make', 'Apple');

        $this->upload($admin, 'foto.jpg', $source->getImageBlob(), 'Een foto');

        $stored = new Imagick;
        $stored->readImageBlob(Storage::disk('local')->get(Image::query()->value('path')));

        $this->assertSame([], array_filter(
            $stored->getImageProperties('exif:*'),
            fn (string $value) => $value !== '',
        ));
    }
}
