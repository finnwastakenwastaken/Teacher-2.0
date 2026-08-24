<?php

namespace App\Http\Controllers;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
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

        // The prompt replaces the content rather than sitting in front of it:
        // nothing about a locked page reaches the browser, so "view source"
        // is not a way past it. Breadcrumbs and the title are shown because
        // they already appear in the public navigation.
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
     * Grouping happens here rather than in the browser because a download
     * tagged for two tracks belongs in both groups — the same attachment is
     * listed twice, deliberately, so a student scanning for "HAVO" finds
     * everything meant for them in one place without having to read tags.
     *
     * Untagged downloads lead in a group of their own. Levels are optional
     * on purpose: a handout meant for everybody should not require ticking
     * every box, and ticking every box would then render it in every group.
     *
     * Empty groups are dropped. A level the owner added for another subject
     * should not leave a bare heading on every page that does not use it.
     *
     * @return array<int, array{key: string, label: string, downloads: array<int, array<string, mixed>>}>
     */
    private function downloadGroups(Page $page): array
    {
        $downloads = $page->downloads()->with(['mediaFile', 'educationLevels'])->get();

        if ($downloads->isEmpty()) {
            return [];
        }

        $card = fn (PageDownload $download) => [
            'ulid' => $download->ulid,
            'label' => $download->displayLabel(),
            'href' => route('downloads.show', $download),
            'kind' => $download->mediaFile->kind,
            'mime' => $download->mediaFile->mime,
            'filename' => $download->mediaFile->original_filename,
            'sizeBytes' => $download->mediaFile->size_bytes,
            'levels' => $download->educationLevels->pluck('name')->all(),
        ];

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
     * The media this page embeds, keyed by ULID.
     *
     * The document only stores identifiers, so the renderer needs somewhere
     * to look up a URL, an alt text or a filename. Built from the reference
     * rows rather than by re-walking the document, so a page can never hand
     * the browser a URL for something it does not actually reference — the
     * same rows that publish a file are the ones that describe it.
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

            $media[$item->ulid] = $item instanceof Image
                ? [
                    'type' => 'image',
                    'url' => route('images.show', $item),
                    'alt' => $item->alt_text,
                    'width' => $item->width,
                    'height' => $item->height,
                ]
                : [
                    'type' => 'file',
                    'url' => route('media.show', $item),
                    'kind' => $item->kind,
                    'mime' => $item->mime,
                    'filename' => $item->original_filename,
                    'sizeBytes' => $item->size_bytes,
                ];
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

        /*
         * 301, because the old address really is gone and a search engine
         * should carry its ranking over to the new one. But a bare 301 is
         * cached by browsers until the profile is cleared, so a slug typo
         * fixed an hour later stays broken for everyone who visited in
         * between — and the owner has no way to reach into their browsers.
         *
         * An explicit max-age bounds that: the redirect is still permanent
         * for a crawler, and a visitor re-asks the server after a day.
         */
        return redirect('/'.$target->fullPath(), HttpResponse::HTTP_MOVED_PERMANENTLY)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
