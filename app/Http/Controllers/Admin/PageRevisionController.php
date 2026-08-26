<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\PageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Looking at, and going back to, a previously published body.
 *
 * Both routes are scoped to their page (`scopeBindings` in routes/admin.php),
 * so a revision id belonging to another page is a 404 rather than another
 * page's body appearing under this one's history.
 */
class PageRevisionController extends Controller
{
    /**
     * One stored body, ready to render.
     *
     * Plain JSON, fetched when the owner opens a version, rather than shipped
     * with the edit screen. Ten bodies in every page-edit payload is exactly
     * the weight that was taken out of `mediaLibrary` — a page carrying a
     * long lesson would send eleven copies of it to draw a list of dates.
     *
     * The media map comes with it because the renderer cannot do without one:
     * a document names its images and files by ULID only, and a ULID missing
     * from the map renders nothing at all. So an old version previewed
     * without one would come out with its pictures silently gone — which is
     * the one thing a preview must not do, since the owner is looking at it
     * to decide whether to restore it.
     */
    public function show(Page $page, PageRevision $revision): JsonResponse
    {
        [$images, $files] = $this->embeddedMedia($revision);

        $media = [];

        foreach ([...$images, ...$files] as $item) {
            $media[$item->ulid] = $item->toPageMediaItem();
        }

        return response()->json([
            'content' => $revision->content,
            'media' => $media,
            // The same files again, in the shape the editor's node views read
            // — see the `library` note on the restore action below. Two
            // projections of one resolved set rather than two lookups, and
            // both come from the models so neither screen can describe a file
            // differently from the other.
            'library' => [
                'images' => array_map(fn (Image $image) => $image->toEditorLibraryEntry(), $images),
                'files' => array_map(fn (MediaFile $file) => $file->toEditorLibraryEntry(), $files),
            ],
        ]);
    }

    /**
     * Publish a stored body again.
     *
     * Through Page::writeContent(), never a column copy. That is what
     * re-derives `content_text` and rebuilds `page_media_references` — the
     * rows that make an embedded file fetchable by an anonymous visitor — so
     * a restored body's pictures and downloads work for students rather than
     * only for the owner, who is authenticated and would see them either way.
     * The same rule App\Actions\DuplicatePage follows, for the same reason.
     *
     * And restoring is itself a publish: writeContent() snapshots the body it
     * replaces, so going back to version 7 appends version 11 rather than
     * rewinding the list. The history is append-only, and looking at an old
     * version can never cost the owner the one they were on.
     *
     * The editor is left to put the restored body on screen itself, from the
     * document and the `library` it was already previewing — see
     * components/editor/version-history.tsx. It has to: TipTap reads its
     * document once, when it is built, so a fresh `page.content` prop changes
     * nothing, and the node views resolve an embed against a library that was
     * loaded with the screen.
     */
    public function restore(Page $page, PageRevision $revision): RedirectResponse
    {
        $page->writeContent($revision->content);

        return to_route('admin.pages.edit', $page)
            ->with('status', __('admin.pages.revision_restored'));
    }

    /**
     * The images and files this stored body embeds.
     *
     * Resolved from the document itself, not from `page_media_references`:
     * those rows describe what the page shows *now*, and an old version is
     * very likely to embed something the current body no longer does — which
     * is precisely the case where the preview has to be right.
     *
     * That it can name a file no public page publishes is not a hole. This
     * route is behind `auth`, and every URL built from it is still served by
     * the same gated controller, which asks App\Support\MediaAccess about the
     * visitor fetching it. An anonymous request for one of them is refused
     * exactly as it was before.
     *
     * @return array{0: list<Image>, 1: list<MediaFile>}
     */
    private function embeddedMedia(PageRevision $revision): array
    {
        $referenced = PageContent::references($revision->content);

        return [
            $referenced['images'] === []
                ? []
                : array_values(Image::query()->whereIn('ulid', $referenced['images'])->get()->all()),
            $referenced['files'] === []
                ? []
                : array_values(MediaFile::query()->whereIn('ulid', $referenced['files'])->get()->all()),
        ];
    }
}
