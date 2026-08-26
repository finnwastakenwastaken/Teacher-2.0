<?php

namespace App\Http\Controllers;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\SlugRedirect;
use App\Models\Topic;
use App\Support\AccessControl;
use App\Support\ContentPathResolver;
use App\Support\IconCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContentController extends Controller
{
    public function show(Request $request, string $path): Response|RedirectResponse
    {
        $node = ContentPathResolver::resolve($path);

        if ($node === null) {
            return $this->redirectFromHistory($path);
        }

        // The prompt replaces the content rather than sitting in front of
        // it — nothing about a locked page reaches the browser.
        if (! AccessControl::allows($node, $request)) {
            return $this->renderLocked($node);
        }

        return $node instanceof Topic
            ? $this->renderTopic($node)
            : $this->renderPage($node);
    }

    private function renderLocked(Topic|Page $node): Response
    {
        $passwordId = AccessControl::effectivePasswordId($node);

        return Inertia::render('content/locked', [
            'title' => $node->title,
            'breadcrumbs' => $this->breadcrumbs($node),
            'path' => $node->fullPath(),
            // Named so a visitor holding more than one password knows which
            // is being asked for. The owner is told these names are visible.
            'passwordName' => AccessPassword::query()->whereKey($passwordId)->value('name'),
        ]);
    }

    private function renderTopic(Topic $topic): Response
    {
        // Build hrefs from the already-resolved $topic path rather than
        // calling ->fullPath() on each child/page: those are fetched with a
        // restricted column list (no parent_id/topic_id), so the belongsTo
        // relation fullPath() relies on can't resolve and would return null.
        $topicPath = $topic->fullPath();

        $childTopics = $topic->childTopics()->visible()->get(['id', 'title', 'slug', 'icon', 'description'])
            ->map(fn (Topic $child) => [...$child->only(['id', 'title', 'slug', 'icon', 'description']), 'href' => '/'.$topicPath.'/'.$child->slug]);
        $pages = $topic->pages()->visible()->get(['id', 'title', 'slug', 'icon', 'description'])
            ->map(fn (Page $page) => [...$page->only(['id', 'title', 'slug', 'icon', 'description']), 'href' => '/'.$topicPath.'/'.$page->slug]);

        return Inertia::render('content/topic', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'content' => $topic->content,
            ],
            'breadcrumbs' => $this->breadcrumbs($topic),
            'childTopics' => $childTopics,
            'pages' => $pages,
            // Geometry for exactly the icons this page draws. See
            // App\Support\IconCatalogue: the alternative is shipping a
            // name-to-chunk map of the whole catalogue to every student.
            'icons' => IconCatalogue::resolve(
                $childTopics->pluck('icon')->merge($pages->pluck('icon'))
            ),
        ]);
    }

    private function renderPage(Page $page): Response
    {
        $page->loadMissing('heroImage');

        return Inertia::render('content/page', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'description' => $page->description,
                'content' => $page->content,
                'hero' => $page->heroImage === null ? null : [
                    'url' => route('images.show', $page->heroImage),
                    'alt' => $page->heroImage->alt_text,
                ],
            ],
            'breadcrumbs' => $this->breadcrumbs($page),
            'media' => $this->referencedMedia($page),
            'downloadGroups' => $this->downloadGroups($page),
        ]);
    }

    /**
     * The downloads section, grouped by education level.
     *
     * Grouped here rather than in the browser: a download tagged for two
     * tracks is deliberately listed in both groups, so a student scanning for
     * "HAVO" finds everything meant for them in one place. Untagged downloads
     * lead in a group of their own — levels are optional, so a handout for
     * everybody need not tick every box. Empty groups are dropped.
     *
     * @return array<int, array{key: string, label: string, downloads: array<int, array<string, mixed>>}>
     */
    private function downloadGroups(Page $page): array
    {
        $downloads = $page->downloads()->with(['mediaFile', 'image', 'educationLevels'])->get();

        if ($downloads->isEmpty()) {
            return [];
        }

        // Both arms of the attachment describe a card the same way — an
        // offered poster is a row in this list like any worksheet, and the
        // only thing that differs is the icon. See PageDownload::kind().
        $card = function (PageDownload $download) {
            $media = $download->offeredMedia();

            return [
                'ulid' => $download->ulid,
                'label' => $download->displayLabel(),
                'href' => route('downloads.show', $download),
                'kind' => $download->kind(),
                'mime' => $media->mime,
                'filename' => $media->original_filename,
                'sizeBytes' => $media->size_bytes,
                'levels' => $download->educationLevels->pluck('name')->all(),
            ];
        };

        $groups = [];

        $untagged = $downloads->filter(fn (PageDownload $d) => $d->educationLevels->isEmpty());

        if ($untagged->isNotEmpty()) {
            $groups[] = [
                'key' => 'all',
                'label' => __('content.downloads.everyone'),
                'downloads' => $untagged->map($card)->values()->all(),
            ];
        }

        foreach (EducationLevel::query()->orderBy('sort_order')->orderBy('name')->get() as $level) {
            $tagged = $downloads->filter(
                fn (PageDownload $d) => $d->educationLevels->contains('id', $level->id)
            );

            if ($tagged->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key' => $level->slug,
                'label' => $level->name,
                'downloads' => $tagged->map($card)->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * The media this page embeds, keyed by ULID — the document only stores
     * identifiers, so the renderer needs a URL/alt/filename lookup. Built
     * from the reference rows rather than re-walking the document, so a page
     * can never hand the browser a URL for something it doesn't reference.
     *
     * @return array<string, array<string, mixed>>
     */
    private function referencedMedia(Page $page): array
    {
        $media = [];

        foreach ($page->mediaReferences()->with('referenceable')->get() as $reference) {
            $item = $reference->referenceable;

            if ($item === null) {
                continue;
            }

            // The shape lives on the models, not here: the page editor's
            // version preview builds the same map from a stored document
            // rather than from these rows, and a preview that described a
            // file differently from the page would be a preview of something
            // else. See App\Models\Image::toPageMediaItem().
            $media[$item->ulid] = $item->toPageMediaItem();
        }

        return $media;
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(Topic|Page $node): array
    {
        $topic = $node instanceof Page ? $node->topic : $node;
        $chain = [];

        while ($topic !== null) {
            $chain[] = ['title' => $topic->title, 'href' => '/'.$topic->fullPath()];
            $topic = $topic->parent;
        }

        $chain = array_reverse($chain);

        if ($node instanceof Page) {
            $chain[] = ['title' => $node->title, 'href' => '/'.$node->fullPath()];
        }

        return $chain;
    }

    private function redirectFromHistory(string $path): RedirectResponse
    {
        $target = SlugRedirect::query()
            ->where('from_path', trim($path, '/'))
            ->first()
            ?->redirectable;

        if ($target === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        // 301 so a search engine carries over its ranking. A bare 301 is
        // cached by browsers indefinitely though, so an explicit max-age
        // makes a visitor re-ask after a day while staying permanent for a
        // crawler.
        return redirect('/'.$target->fullPath(), HttpResponse::HTTP_MOVED_PERMANENTLY)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
