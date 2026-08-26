<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DependentRecordsExistException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MediaFile;
use App\Support\MediaFormats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two media libraries: images (alt text required) and documents/videos.
 * Kept separate because they are used for different things and have
 * different required metadata — see the technical reference.
 *
 * What each item is *for* is derived, never authored. The answer already
 * exists in data — page_media_references says what a page shows, page_downloads
 * says what a page hands out — and a "this one is for downloading" flag the
 * owner had to set would force a lie the first time a diagram was both
 * embedded in the lesson and offered as a printable handout. That is a real
 * case, and it comes out of these counts as both badges at once.
 */
class MediaLibraryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/media/index', [
            'images' => Image::query()
                // The banner counts as "shown on a page": it is page furniture
                // rendered above the title, and calling it unused would invite
                // the owner to delete it. The delete guard already knows.
                ->withCount(['pageReferences', 'pageDownloads', 'heroForPages'])
                ->latest()
                ->get(['id', 'ulid', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename', 'created_at'])
                ->map(fn (Image $image) => [
                    ...$image->only([
                        'ulid', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename',
                    ]),
                    'url' => route('images.show', $image),
                    'shownOnPage' => $image->page_references_count > 0
                        || $image->hero_for_pages_count > 0,
                    'offeredAsDownload' => $image->page_downloads_count > 0,
                ]),
            'files' => MediaFile::query()
                ->withCount(['pageReferences', 'pageDownloads'])
                ->latest()
                ->get(['id', 'ulid', 'kind', 'size_bytes', 'mime', 'original_filename', 'created_at'])
                ->map(fn (MediaFile $file) => [
                    ...$file->only(['ulid', 'kind', 'size_bytes', 'mime', 'original_filename']),
                    'url' => route('media.show', $file),
                    'shownOnPage' => $file->page_references_count > 0,
                    'offeredAsDownload' => $file->page_downloads_count > 0,
                ]),
            'maxBytes' => (int) config('media.max_bytes'),
            // The other half of what the server decides about an upload, and
            // it travels the same way for the same reason: the uploader can
            // only state the accepted formats honestly if it is handed them.
            'acceptedFormats' => MediaFormats::byKind(),
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
            // Thrown from a `deleting` model event, invisible to PHPStan from
            // here, so it flags this catch as dead. It is not: removing it
            // turns "still in use" into a 500.
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
            // Thrown from a `deleting` model event, invisible to PHPStan from
            // here, so it flags this catch as dead. It is not: removing it
            // turns "still in use" into a 500.
            // @phpstan-ignore catch.neverThrown
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.media.file_deleted'));
    }
}
