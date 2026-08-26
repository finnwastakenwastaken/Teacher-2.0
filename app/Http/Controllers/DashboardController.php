<?php

namespace App\Http\Controllers;

use App\Models\AccessPassword;
use App\Models\EducationLevel;
use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use App\Support\SiteSettings;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin landing page — the first screen after claiming the account.
 * Carries `nextSteps` because this site has no setup wizard (every setting
 * already has a working default), so the checklist tells a fresh install
 * what to do first and disappears once the site is in use. Everything here
 * is read-only: nothing changes on this screen that can't also be changed on
 * the screen it links to.
 */
class DashboardController extends Controller
{
    public function show(): Response
    {
        $counts = $this->counts();

        return Inertia::render('dashboard', [
            'stats' => $counts,
            'nextSteps' => $this->nextSteps($counts),
            /*
             * Deliberately its own prop rather than a seventh item in
             * nextSteps.
             *
             * That checklist is a first-run affordance: every step is
             * something done once, and the whole block disappears when they
             * all are. An outstanding concept is neither — it recurs for the
             * life of the site, and folding it in would resurrect a
             * finished checklist (with its six ticks) every time the owner
             * left a page half-written. "Done" would also have to mean two
             * different things in one list.
             */
            'draftPages' => $this->draftPages(),
            'recentPages' => $this->recentPages(),
            'popularDownloads' => $this->popularDownloads(),
        ]);
    }

    /**
     * @return array<string, int|bool>
     */
    private function counts(): array
    {
        // One query per table, not one per number: `count(*) filter (where …)`
        // lets Postgres compute every column in a single pass.
        $topics = $this->aggregate(Topic::query()->toBase(), [
            'topics' => 'count(*)',
            'hiddenTopics' => 'count(*) filter (where is_hidden)',
        ]);

        $pages = $this->aggregate(Page::query()->toBase(), [
            'pages' => 'count(*)',
            'hiddenPages' => 'count(*) filter (where is_hidden)',
            'emptyPages' => 'count(*) filter (where content is null)',
        ]);

        $images = $this->aggregate(Image::query()->toBase(), [
            'images' => 'count(*)',
            'imageBytes' => 'coalesce(sum(size_bytes), 0)',
        ]);

        $files = $this->aggregate(MediaFile::query()->toBase(), [
            'documents' => "count(*) filter (where kind = '".MediaFile::KIND_DOCUMENT."')",
            'videos' => "count(*) filter (where kind = '".MediaFile::KIND_VIDEO."')",
            'fileBytes' => 'coalesce(sum(size_bytes), 0)',
        ]);

        $downloads = $this->aggregate(PageDownload::query()->toBase(), [
            'downloads' => 'count(*)',
            // The site's only counter, and an aggregate with no visitor data
            // attached to it — see the technical reference. Authenticated requests are not
            // counted, so this is what students actually took.
            'downloadsServed' => 'coalesce(sum(downloads_count), 0)',
        ]);

        return [
            ...$topics,
            ...$pages,
            'images' => $images['images'],
            'documents' => $files['documents'],
            'videos' => $files['videos'],
            'mediaBytes' => $images['imageBytes'] + $files['fileBytes'],
            ...$downloads,
            'levels' => EducationLevel::query()->count(),
            'passwords' => AccessPassword::query()->count(),
        ];
    }

    /**
     * Run several aggregates over one table in one query. The expressions
     * are literals written above, never request input, so selectRaw is safe
     * here — there is no bindable form of a `filter` clause anyway.
     *
     * @param  array<string, string>  $expressions
     * @return array<string, int>
     */
    private function aggregate(QueryBuilder $query, array $expressions): array
    {
        $select = [];

        foreach ($expressions as $alias => $expression) {
            $select[] = $expression.' as "'.$alias.'"';
        }

        // Assembled from the literals above, never from input — see the doc
        // block. PHPStan wants a literal-string and cannot see that.
        // @phpstan-ignore argument.type
        $row = (array) $query->selectRaw(implode(', ', $select))->first();

        return array_map(intval(...), $row);
    }

    /**
     * The first-run checklist, in the order the work naturally happens. Each
     * step is shown with its state rather than hidden once done, so the
     * owner sees what is left rather than watching items vanish.
     *
     * @param  array<string, int|bool>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function nextSteps(array $counts): array
    {
        // A title the owner has never touched still resolves to APP_NAME.
        $named = SiteSettings::get('site_title') !== config('app.name');

        $steps = [
            'branding' => [
                'href' => route('admin.site-settings.edit'),
                'done' => $named,
            ],
            'topic' => [
                'href' => route('admin.topics.create'),
                'done' => $counts['topics'] > 0,
            ],
            'page' => [
                'href' => route('admin.pages.create'),
                'done' => $counts['pages'] > 0,
            ],
            'content' => [
                'href' => route('admin.topics.index'),
                'done' => $counts['pages'] > 0 && $counts['emptyPages'] < $counts['pages'],
            ],
            'media' => [
                'href' => route('admin.media.index'),
                'done' => $counts['images'] + $counts['documents'] + $counts['videos'] > 0,
            ],
            'download' => [
                'href' => route('admin.topics.index'),
                'done' => $counts['downloads'] > 0,
            ],
        ];

        // The copy lives in lang/, keyed by the same name, so the two locales
        // stay in step and LocalisationTest can see both halves. What stays
        // here is the part that is not copy: where each step goes and how it
        // knows it is done.
        return array_map(
            fn (array $step, string $key) => [
                'key' => $key,
                'title' => (string) __("admin.dashboard.steps.{$key}.title"),
                'description' => (string) __("admin.dashboard.steps.{$key}.description"),
                ...$step,
            ],
            $steps,
            array_keys($steps),
        );
    }

    /**
     * Pages carrying writing the site is not showing.
     *
     * The editor opens on the concept and keeps saving it, which is right for
     * writing and leaves one thing nothing else surfaces: a page can sit for
     * weeks showing the owner one body and students another, discoverable
     * only by opening that page. This is the answer, and it links to the
     * screen that can act on it.
     *
     * `draft_saved_at` is the question, not `draft_content` — see
     * Page::hasDraft(). Selecting the columns explicitly keeps a jsonb body
     * per row out of a list that only needs a title, and the concept stays
     * out of the response for the same reason it is in Page::$hidden.
     *
     * @return array<int, array<string, mixed>>
     */
    private function draftPages(): array
    {
        return Page::query()
            ->whereNotNull('draft_saved_at')
            ->orderByDesc('draft_saved_at')
            ->get(['id', 'title', 'draft_saved_at'])
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'savedAt' => $page->draft_saved_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPages(): array
    {
        return Page::query()
            ->with('topic.parent.parent')
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'path' => '/'.$page->fullPath(),
                'isHidden' => $page->is_hidden,
                'isEmpty' => $page->content === null,
                // Free here — these are whole models — and the list already
                // reports a page's state in badges, so leaving this one state
                // out would be the odd omission rather than the tidy one.
                'hasDraft' => $page->hasDraft(),
                'updatedAt' => $page->updated_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function popularDownloads(): array
    {
        return PageDownload::query()
            // Both arms: displayLabel() falls back to the file's own name, and
            // an image-backed attachment would otherwise load it one row at a
            // time.
            ->with(['page', 'mediaFile', 'image'])
            ->where('downloads_count', '>', 0)
            ->orderByDesc('downloads_count')
            ->take(5)
            ->get()
            ->map(fn (PageDownload $download) => [
                'id' => $download->id,
                'label' => $download->displayLabel(),
                'page' => $download->page->title,
                'count' => $download->downloads_count,
            ])
            ->all();
    }
}
