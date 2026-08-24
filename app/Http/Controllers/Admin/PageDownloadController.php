<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageDownloadRequest;
use App\Http\Requests\Admin\UpdatePageDownloadRequest;
use App\Models\Page;
use App\Models\PageDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * The downloads section of a page: which files it offers and to which tracks.
 *
 * Attaching a file here publishes it — App\Support\MediaAccess treats a
 * download attachment as a reason an anonymous visitor may fetch the bytes,
 * exactly as a body embed is. Detaching it makes it private again.
 */
class PageDownloadController extends Controller
{
    public function store(StorePageDownloadRequest $request, Page $page): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($page, $validated): void {
            $download = $page->downloads()->create([
                'media_file_id' => $validated['media_file_id'],
                'label' => $validated['label'] ?? null,
                'sort_order' => $validated['sort_order'],
            ]);

            $download->educationLevels()->sync($validated['education_levels'] ?? []);
        });

        return back()->with('status', __('admin.downloads.added'));
    }

    public function update(UpdatePageDownloadRequest $request, PageDownload $pageDownload): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($pageDownload, $validated): void {
            $pageDownload->update([
                'label' => $validated['label'] ?? null,
                'sort_order' => $validated['sort_order'],
            ]);

            $pageDownload->educationLevels()->sync($validated['education_levels'] ?? []);
        });

        return back()->with('status', __('admin.downloads.updated'));
    }

    public function destroy(PageDownload $pageDownload): RedirectResponse
    {
        // The pivot cascades with the row. Nothing else points at it, and the
        // file itself stays in the library — removing a download unpublishes
        // the file, it does not delete it.
        $pageDownload->delete();

        return back()->with('status', __('admin.downloads.deleted'));
    }
}
