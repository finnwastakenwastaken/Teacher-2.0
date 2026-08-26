<?php

namespace Tests\Feature\Media;

use App\Exceptions\DependentRecordsExistException;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deletes that would orphan data block and report what depends on them; they
 * never cascade (the technical reference). Nothing references a media file yet, so the
 * blocking path is exercised through a subclass that reports dependents —
 * pinning the behaviour now so later tasks just fill in dependents().
 */
class MediaDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeImage(): Image
    {
        $path = 'images/2026/08/'.Str::ulid().'.png';
        Storage::disk('local')->put($path, 'bytes');

        return Image::query()->create([
            'path' => $path,
            'alt_text' => 'Een diagram',
            'size_bytes' => 5,
            'mime' => 'image/png',
            'original_filename' => 'diagram.png',
        ]);
    }

    public function test_deleting_an_unused_image_removes_the_row_and_the_file()
    {
        $image = $this->makeImage();
        $path = $image->path;

        $image->delete();

        $this->assertModelMissing($image);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_an_unused_media_file_removes_the_row_and_the_file()
    {
        $path = 'media/2026/08/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, 'bytes');

        $file = MediaFile::query()->create([
            'path' => $path,
            'kind' => MediaFile::KIND_DOCUMENT,
            'mime' => 'application/pdf',
            'size_bytes' => 5,
            'original_filename' => 'werkblad.pdf',
        ]);

        $file->delete();

        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_an_image_still_in_use_cannot_be_deleted_and_keeps_its_file()
    {
        $image = $this->makeImage();
        $path = $image->path;

        $inUse = ImageWithDependents::query()->find($image->id);

        try {
            $inUse->delete();
            $this->fail('Expected the delete to be blocked.');
        } catch (DependentRecordsExistException $e) {
            // The message has to name what is using it, otherwise the owner
            // is told "no" with no way to act on it.
            $this->assertStringContainsString('De Planeten', $e->getMessage());
        }

        $this->assertModelExists($image);
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_admin_can_delete_an_image_through_the_library()
    {
        $image = $this->makeImage();

        $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.images.destroy', $image))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($image);
    }

    public function test_alt_text_cannot_be_blanked()
    {
        $image = $this->makeImage();

        $this->actingAs(User::factory()->create())
            ->from(route('admin.media.index'))
            ->patch(route('admin.media.images.update', $image), ['alt_text' => '   '])
            ->assertSessionHasErrors('alt_text');

        $this->assertSame('Een diagram', $image->fresh()->alt_text);
    }

    public function test_guests_cannot_reach_the_media_library()
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));
    }
}

/**
 * Stands in for an image that a page is using, so the block-and-report path
 * can be tested before the tables that create real usages exist.
 */
class ImageWithDependents extends Image
{
    protected $table = 'images';

    public function dependents(): array
    {
        return ['Gebruikt op' => ['De Planeten', 'Zwaartekracht']];
    }
}
