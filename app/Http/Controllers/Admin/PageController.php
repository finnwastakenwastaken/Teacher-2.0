<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DuplicatePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Support\IconCatalogue;
use App\Support\SortOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Persist a drag-and-drop reorder of the pages under one topic.
     *
     * Reordering only — see App\Support\SortOrder for why dragging must not
     * be able to move a page to a different topic.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        SortOrder::apply(Page::query(), $ids, 'topic_id');

        return back();
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/create', [
            'topics' => Topic::query()->orderBy('title')->get(['id', 'title', 'depth']),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
            // For the banner picker. By id, because hero_image_id is a
            // relational write — the editor's own library is by ULID.
            'images' => Image::query()->latest('id')->get(['id', 'ulid', 'alt_text', 'original_filename'])
                ->map(fn (Image $image) => [
                    'id' => $image->id,
                    'alt' => $image->alt_text,
                    'filename' => $image->original_filename,
                    'url' => route('images.show', $image),
                ]),
        ]);
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        try {
            Page::query()->create($request->validated());
        } catch (QueryException) {
            return back()->withErrors(['slug' => 'Deze wijziging kon niet worden opgeslagen.'])->withInput();
        }

        return to_route('admin.topics.index')->with('status', 'Pagina aangemaakt.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/edit', [
            'page' => $page,
            // Geometry for the icon already chosen, so the picker can draw it
            // without asking the server a second time.
            'iconData' => IconCatalogue::resolve([$page->icon])[$page->icon] ?? null,
            'topics' => Topic::query()->orderBy('title')->get(['id', 'title', 'depth']),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
            // For the banner picker. By id, because hero_image_id is a
            // relational write — the editor's own library is by ULID.
            'images' => Image::query()->latest('id')->get(['id', 'ulid', 'alt_text', 'original_filename'])
                ->map(fn (Image $image) => [
                    'id' => $image->id,
                    'alt' => $image->alt_text,
                    'filename' => $image->original_filename,
                    'url' => route('images.show', $image),
                ]),
            // The editor needs to show what an embed actually points at, and
            // it cannot fetch gated media metadata itself.
            'mediaLibrary' => [
                'images' => Image::query()->latest()->get(['ulid', 'alt_text', 'original_filename'])
                    ->map(fn (Image $image) => [
                        ...$image->only(['ulid', 'alt_text', 'original_filename']),
                        'url' => route('images.show', $image),
                    ]),
                'files' => MediaFile::query()->latest()->get(['ulid', 'kind', 'mime', 'size_bytes', 'original_filename'])
                    ->map(fn (MediaFile $file) => [
                        ...$file->only(['ulid', 'kind', 'mime', 'size_bytes', 'original_filename']),
                        'url' => route('media.show', $file),
                    ]),
            ],
            'educationLevels' => EducationLevel::query()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'slug']),
            // Separate from mediaLibrary above: attaching a download is a
            // relational write keyed by id, while the editor addresses media
            // by ULID and must never learn the id.
            'downloadFiles' => MediaFile::query()->orderBy('original_filename')
                ->get(['id', 'ulid', 'kind', 'mime', 'size_bytes', 'original_filename']),
            'downloads' => $page->downloads()->with(['mediaFile', 'educationLevels'])->get()
                ->map(fn (PageDownload $download) => [
                    'ulid' => $download->ulid,
                    'label' => $download->label,
                    'sortOrder' => $download->sort_order,
                    'downloadsCount' => $download->downloads_count,
                    'mediaFileId' => $download->media_file_id,
                    'filename' => $download->mediaFile->original_filename,
                    'kind' => $download->mediaFile->kind,
                    'mime' => $download->mediaFile->mime,
                    'sizeBytes' => $download->mediaFile->size_bytes,
                    'educationLevelIds' => $download->educationLevels->pluck('id')->all(),
                ]),
            // The editor uploads too, so the same ceiling the media screen
            // shows has to be here as well.
            'uploadMaxBytes' => (int) config('media.max_bytes'),
        ]);
    }

    /**
     * Save the page body.
     *
     * Separate from update() because it is a different interaction: the
     * settings form is submitted deliberately, while the editor saves a
     * document. Validation here is only that it *is* a document — what may
     * be inside one is decided by the whitelist in App\Support\PageContent,
     * not by a Form Request, because it has to be applied recursively.
     */
    public function updateContent(Request $request, Page $page): RedirectResponse
    {
        $request->validate([
            'content' => ['nullable', 'array'],
            'content.type' => ['required_with:content', 'string', 'in:doc'],
        ], [
            'content.type.in' => 'De inhoud van deze pagina kon niet worden gelezen.',
        ]);

        // Deliberately input(), not validated(). validated() returns only the
        // keys that have rules, so it would hand back a document stripped
        // down to ['type' => 'doc'] and silently erase the entire body. The
        // rules above check that this *is* a document; what may be inside it
        // is decided recursively by PageContent::sanitise().
        $page->writeContent($request->input('content'));

        return back()->with('status', 'Pagina-inhoud opgeslagen.');
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        try {
            DB::transaction(fn () => $page->update($request->validated()));
        } catch (QueryException) {
            return back()->withErrors(['slug' => 'Deze wijziging kon niet worden opgeslagen.'])->withInput();
        }

        return to_route('admin.topics.index')->with('status', 'Pagina bijgewerkt.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.topics.index')->with('status', 'Pagina verwijderd.');
    }

    /**
     * Copy a page, body and downloads and all.
     *
     * Lands on the copy's edit screen rather than back in the list: nobody
     * duplicates a page in order to look at it, and the copy is hidden until
     * they finish — see App\Actions\DuplicatePage.
     */
    public function duplicate(Page $page, DuplicatePage $duplicator): RedirectResponse
    {
        $copy = $duplicator->handle($page);

        return to_route('admin.pages.edit', $copy)
            ->with('status', 'Pagina gekopieerd. De kopie staat op verborgen tot je hem publiceert.');
    }
}
