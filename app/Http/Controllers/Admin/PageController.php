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
use App\Models\PageMediaReference;
use App\Models\Topic;
use App\Support\IconCatalogue;
use App\Support\SortOrder;
use App\Support\TreeConstraintViolation;
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
            // Nothing: a new page has no banner yet, so there is nothing for
            // the picker to draw. It searches for the rest.
            'heroImage' => null,
        ]);
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        try {
            Page::query()->create($request->validated());
        } catch (QueryException $e) {
            return $this->refuse($e);
        }

        return to_route('admin.topics.index')->with('status', __('admin.pages.created'));
    }

    public function edit(Page $page): Response
    {
        $downloads = $page->downloads()->with(['mediaFile', 'educationLevels'])->get();
        $attachedFileIds = $downloads->pluck('media_file_id')->all();

        return Inertia::render('admin/pages/edit', [
            'page' => $page,
            // Geometry for the icon already chosen, so the picker can draw it
            // without asking the server a second time.
            'iconData' => IconCatalogue::resolve([$page->icon])[$page->icon] ?? null,
            'topics' => Topic::query()->orderBy('title')->get(['id', 'title', 'depth']),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
            // Only what the banner currently points at, so the picker can
            // draw its thumbnail. By id, because hero_image_id is a
            // relational write — the editor's own library is by ULID, and
            // that is why there are two image-search endpoints.
            'heroImage' => $page->heroImage?->toPickerOption(),
            // Only what the body already embeds, resolved from the derived
            // page_media_references rows — not the whole library. The picker
            // dialogs ask App\Http\Controllers\Admin\MediaSearchController
            // for anything else, a page of matches at a time. This is what
            // lets the node views (resources/js/components/editor/extensions/*)
            // draw an existing embed the instant the editor mounts, the same
            // way `iconData` above does for the page icon.
            'mediaLibrary' => $this->embeddedMedia($page),
            'educationLevels' => EducationLevel::query()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'downloads' => $downloads
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
            // Whether the "choose a file" button in the downloads section has
            // anything to offer — a single boolean rather than the list it
            // would otherwise have to ship just to find that out client-side.
            'attachableFilesAvailable' => MediaFile::query()
                ->when($attachedFileIds !== [], fn ($query) => $query->whereNotIn('id', $attachedFileIds))
                ->exists(),
            // The editor uploads too, so the same ceiling the media screen
            // shows has to be here as well.
            'uploadMaxBytes' => (int) config('media.max_bytes'),
        ]);
    }

    /**
     * Geometry for the images and files this page's body already embeds.
     *
     * Sourced from page_media_references — the same rows that decide what is
     * published (see Page::writeContent()) — rather than re-walking the
     * stored document, so this can never disagree with what the database
     * already believes the body shows.
     *
     * @return array{images: array<int, array<string, mixed>>, files: array<int, array<string, mixed>>}
     */
    private function embeddedMedia(Page $page): array
    {
        $referenced = $page->mediaReferences()->with('referenceable')->get()
            ->map(fn (PageMediaReference $reference) => $reference->referenceable)
            ->filter();

        return [
            'images' => $referenced->whereInstanceOf(Image::class)
                ->map(fn (Image $image) => [
                    'ulid' => $image->ulid,
                    'alt_text' => $image->alt_text,
                    'original_filename' => $image->original_filename,
                    'url' => route('images.show', $image),
                ])
                ->values()
                ->all(),
            'files' => $referenced->whereInstanceOf(MediaFile::class)
                ->map(fn (MediaFile $file) => [
                    ...$file->only(['id', 'ulid', 'kind', 'mime', 'size_bytes', 'original_filename']),
                    'url' => route('media.show', $file),
                ])
                ->values()
                ->all(),
        ];
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
            // `present`, not just `nullable`. An absent key and an explicit
            // null mean different things here: null is "the owner emptied
            // this page", absence is a malformed request — and with nullable
            // alone the two were the same, so anything that failed to send
            // the field replaced the whole body with nothing. `present`
            // makes absence a validation error and leaves clearing intact.
            'content' => ['present', 'nullable', 'array'],
            'content.type' => ['required_with:content', 'string', 'in:doc'],
        ], [
            'content.type.in' => __('admin.pages.content_unreadable'),
        ]);

        // Deliberately input(), not validated(). validated() returns only the
        // keys that have rules, so it would hand back a document stripped
        // down to ['type' => 'doc'] and silently erase the entire body. The
        // rules above check that this *is* a document; what may be inside it
        // is decided recursively by PageContent::sanitise().
        $page->writeContent($request->input('content'));

        return back()->with('status', __('admin.pages.content_saved'));
    }

    public function update(UpdatePageRequest $request, Page $page): RedirectResponse
    {
        try {
            DB::transaction(fn () => $page->update($request->validated()));
        } catch (QueryException $e) {
            return $this->refuse($e);
        }

        return to_route('admin.topics.index')->with('status', __('admin.pages.updated'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.topics.index')->with('status', __('admin.pages.deleted'));
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
            ->with('status', __('admin.pages.duplicated'));
    }

    /**
     * Show the owner what they can fix — or let a real failure be a failure.
     *
     * The previous catch put __('admin.pages.save_failed') on
     * the slug field for every QueryException, so a connection drop, a full
     * disk and a genuine slug clash were the same message on a field that may
     * well be fine — and none of them reached the log.
     */
    private function refuse(QueryException $e): RedirectResponse
    {
        $message = TreeConstraintViolation::message($e);

        report($e);

        if ($message === null) {
            throw $e;
        }

        return back()->withErrors(['slug' => $message])->withInput();
    }
}
