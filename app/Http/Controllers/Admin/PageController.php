<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DuplicatePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageDraftRequest;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\PageMediaReference;
use App\Models\PageRevision;
use App\Models\Topic;
use App\Support\IconCatalogue;
use App\Support\MediaFormats;
use App\Support\SortOrder;
use App\Support\TreeConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
        $downloads = $page->downloads()->with(['mediaFile', 'image', 'educationLevels'])->get();
        $attachedFileIds = $downloads->pluck('media_file_id')->filter()->values()->all();
        $attachedImageIds = $downloads->pluck('image_id')->filter()->values()->all();

        return Inertia::render('admin/pages/edit', [
            'page' => $page,
            // The unpublished concept, if there is one. The editor opens on
            // it, so the owner carries straight on from where they stopped
            // and their newest writing is never the thing at risk. Sent
            // alongside the published body rather than in place of it: the
            // screen is then showing something the site is not, and
            // DraftNotice is what keeps that honest — it needs both halves to
            // say which is which.
            'draft' => $page->hasDraft()
                ? [
                    'content' => $page->draft_content,
                    'savedAt' => $page->draft_saved_at?->toIso8601String(),
                ]
                : null,
            // The version history: when each previously published body was
            // replaced, newest first, and nothing else. The bodies themselves
            // are fetched one at a time when the owner opens one — see
            // App\Http\Controllers\Admin\PageRevisionController. Ten copies of
            // a long lesson in every edit payload is exactly the weight that
            // was taken out of `mediaLibrary`.
            //
            // A date and a time is the whole label, because it is the only
            // thing that tells two versions apart. The formatting is the
            // browser's — the interface language is the visitor's choice and
            // is decided there.
            'revisions' => $page->revisions()->get(['id', 'created_at'])
                ->map(fn (PageRevision $revision) => [
                    'id' => $revision->id,
                    'savedAt' => $revision->created_at?->toIso8601String(),
                ]),
            // Geometry for the icon already chosen, so the picker can draw it
            // without asking the server a second time.
            'iconData' => IconCatalogue::resolve([$page->icon])[$page->icon] ?? null,
            'topics' => Topic::query()->orderBy('title')->get(['id', 'title', 'depth']),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
            // Only what the banner currently points at, by id (hero_image_id
            // is a relational write) — the editor's own library is by ULID,
            // hence two image-search endpoints.
            'heroImage' => $page->heroImage?->toPickerOption(),
            // Only what the body already embeds (from page_media_references),
            // not the whole library. MediaSearchController serves the rest,
            // a page of matches at a time.
            'mediaLibrary' => $this->embeddedMedia($page),
            'educationLevels' => EducationLevel::query()->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'slug']),
            // `source` and `mediaId` rather than one `mediaFileId`, because an
            // attachment now names either library and the picker has to
            // exclude what is already here from the right one. Everything else
            // is asked of App\Models\PageDownload, which is the one place the
            // two arms are told apart.
            'downloads' => $downloads
                ->map(fn (PageDownload $download) => [
                    'ulid' => $download->ulid,
                    'label' => $download->label,
                    'sortOrder' => $download->sort_order,
                    'downloadsCount' => $download->downloads_count,
                    'source' => $download->image_id === null ? 'file' : 'image',
                    'mediaId' => $download->media_file_id ?? $download->image_id,
                    'filename' => $download->offeredMedia()->original_filename,
                    'kind' => $download->kind(),
                    'mime' => $download->offeredMedia()->mime,
                    'sizeBytes' => $download->offeredMedia()->size_bytes,
                    // A thumbnail for an offered picture, so a page handing out
                    // three posters is readable in the admin list. Gated like
                    // every other media URL; the owner is authenticated.
                    'previewUrl' => $download->image === null
                        ? null
                        : route('images.show', $download->image),
                    'educationLevelIds' => $download->educationLevels->pluck('id')->all(),
                ]),
            // Whether the "choose from the library" button in the downloads
            // section has anything to offer — a single boolean rather than the
            // lists it would otherwise have to ship just to find that out
            // client-side. Either library counts: a page whose every document
            // is already attached can still be handed a poster.
            'attachableMediaAvailable' => MediaFile::query()
                ->when($attachedFileIds !== [], fn ($query) => $query->whereNotIn('id', $attachedFileIds))
                ->exists()
                || Image::query()
                    ->when($attachedImageIds !== [], fn ($query) => $query->whereNotIn('id', $attachedImageIds))
                    ->exists(),
            // The editor uploads too, so the same ceiling the media screen
            // shows has to be here as well — and the same list of formats,
            // for the same reason. Both are the server's decision, and an
            // uploader that has not been told them can only guess out loud.
            'uploadMaxBytes' => (int) config('media.max_bytes'),
            'acceptedFormats' => MediaFormats::byKind(),
        ]);
    }

    /**
     * Geometry for the images and files this page's body already embeds.
     * Sourced from page_media_references — the same rows that decide what is
     * published (Page::writeContent()) — rather than re-walking the stored
     * document, so this can never disagree with the database.
     *
     * @return array{images: array<int, array<string, mixed>>, files: array<int, array<string, mixed>>}
     */
    private function embeddedMedia(Page $page): array
    {
        $referenced = $page->mediaReferences()->with('referenceable')->get()
            ->map(fn (PageMediaReference $reference) => $reference->referenceable)
            ->filter();

        // The shapes live on the models: the version preview
        // (App\Http\Controllers\Admin\PageRevisionController) sends the same
        // two, for a body this page does not currently show, and an embed
        // that resolved on one screen and not the other reads to the owner
        // as "these images no longer exist".
        return [
            'images' => $referenced->whereInstanceOf(Image::class)
                ->map(fn (Image $image) => $image->toEditorLibraryEntry())
                ->values()
                ->all(),
            'files' => $referenced->whereInstanceOf(MediaFile::class)
                ->map(fn (MediaFile $file) => $file->toEditorLibraryEntry())
                ->values()
                ->all(),
        ];
    }

    /**
     * Autosave a concept.
     *
     * A plain JSON endpoint, not an Inertia visit: Inertia cancels an
     * in-flight visit the moment a new one starts, so an autosave firing
     * between the editor's other visits (attaching an upload, saving
     * settings) would either be cancelled or cancel them. It has nothing to
     * re-render either — only a "saved at" timestamp.
     *
     * Page::writeDraft() is what makes this safe on a timer: it writes one
     * column and touches nothing derived, so an autosave cannot publish a
     * file or put an unfinished page in search.
     */
    public function storeDraft(StorePageDraftRequest $request, Page $page): JsonResponse
    {
        // input(), not validated(), for the reason given on the request class
        // and again below: validated() returns only the keys that have rules,
        // which here is the document flattened to ['type' => 'doc'].
        $page->writeDraft($request->input('content'));

        return response()->json([
            'savedAt' => $page->draft_saved_at?->toIso8601String(),
        ]);
    }

    /**
     * Throw the concept away; the published body is untouched.
     *
     * An Inertia visit rather than JSON, unlike the autosave above: the
     * editor has to come back showing the published body, which means a fresh
     * `page` prop rather than a value the client patches in for itself.
     */
    public function destroyDraft(Page $page): RedirectResponse
    {
        $page->discardDraft();

        return back()->with('status', __('admin.pages.draft_discarded'));
    }

    /**
     * Publish the page body.
     *
     * Separate from update() because it is a different interaction: the
     * settings form is submitted deliberately, while the editor saves a
     * document. Validation here is only that it *is* a document — what may
     * be inside one is decided by the whitelist in App\Support\PageContent.
     *
     * Publishing is always a *promote*: the document is written to the
     * concept column and then promoted through Page::writeContent(), so
     * there is exactly one place the media references, derived text and
     * search vector are rebuilt, and a publish interrupted mid-way leaves
     * the owner's work saved as a concept rather than lost.
     */
    public function updateContent(Request $request, Page $page): RedirectResponse
    {
        $request->validate([
            // `present`, not just `nullable`: absence must be a validation
            // error, not silently treated the same as an explicit null that
            // means "the owner emptied this page".
            'content' => ['present', 'nullable', 'array'],
            'content.type' => ['required_with:content', 'string', 'in:doc'],
        ], [
            'content.type.in' => __('admin.pages.content_unreadable'),
        ]);

        // input(), not validated(): validated() only returns keys with rules,
        // which would strip the document to ['type' => 'doc'] and erase the
        // body. PageContent::sanitise() is what decides what survives.
        $page->writeDraft($request->input('content'));
        $page->promoteDraft();

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
     * Only the tree-constraint violations get a friendly slug-field message;
     * anything else is reported and rethrown rather than mislabelled.
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
