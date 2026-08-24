<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DependentRecordsExistException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two media libraries: images (alt text required) and documents/videos.
 * Kept separate because they are used for different things and have
 * different required metadata — see the technical reference.
 */
class MediaLibraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/media/index', [
            'images' => Image::query()
                ->latest()
                ->get(['id', 'ulid', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename', 'created_at'])
                ->map(fn (Image $image) => [
                    ...$image->only([
                        'ulid', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename',
                    ]),
                    'url' => route('images.show', $image),
                ]),
            'files' => MediaFile::query()
                ->latest()
                ->get(['id', 'ulid', 'kind', 'size_bytes', 'mime', 'original_filename', 'created_at'])
                ->map(fn (MediaFile $file) => [
                    ...$file->only(['ulid', 'kind', 'size_bytes', 'mime', 'original_filename']),
                    'url' => route('media.show', $file),
                ]),
            'maxBytes' => (int) config('media.max_bytes'),
        ]);
    }

    public function updateImage(Request $request, Image $image): RedirectResponse
    {
        $validated = $request->validate([
            // Alt text is required on every image and cannot be blanked
            // afterwards. Also enforced by a CHECK constraint on the table
            // and by the media library service.
            'alt_text' => ['required', 'string', 'max:500'],
        ], [
            'alt_text.required' => __('admin.media.alt_required'),
        ]);

        $image->update($validated);

        return back()->with('status', __('admin.media.image_updated'));
    }

    public function destroyImage(Image $image): RedirectResponse
    {
        try {
            $image->delete();
            // Thrown from a `deleting` model event, which PHPStan cannot
            // see from here — so it reports this catch as dead. It is not:
            // remove it and "this still has things depending on it" becomes
            // a 500. The guard lives on the model exactly so that no delete
            // path can skip it.
            // @phpstan-ignore catch.neverThrown
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.media.image_deleted'));
    }

    public function destroyFile(MediaFile $mediaFile): RedirectResponse
    {
        try {
            $mediaFile->delete();
            // Thrown from a `deleting` model event, which PHPStan cannot
            // see from here — so it reports this catch as dead. It is not:
            // remove it and "this still has things depending on it" becomes
            // a 500. The guard lives on the model exactly so that no delete
            // path can skip it.
            // @phpstan-ignore catch.neverThrown
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.media.file_deleted'));
    }
}
