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
 * The admin landing page.
 *
 * This is the first screen the owner sees after claiming the account, which
 * is why it carries `nextSteps`: this site deliberately has no setup wizard
 * (every setting has a working default — see App\Support\SiteSettings, and
 * the education levels are seeded on boot), so the checklist is what tells a
 * brand-new install what to do first. It disappears on its own once the site
 * is genuinely in use.
 *
 * Everything here is read-only. Nothing can be changed from this screen that
 * cannot be changed on the screen it links to, so the dashboard never
 * becomes a second place where a rule has to be enforced.
 */
class DashboardController extends Controller
{
    public function show(): Response
    {
        $counts = $this->counts();

        return Inertia::render('dashboard', [
            'stats' => $counts,
            'nextSteps' => $this->nextSteps($counts),
            'recentPages' => $this->recentPages(),
            'popularDownloads' => $this->popularDownloads(),
        ]);
    }

    /**
     * @return array<string, int|bool>
     */
    private function counts(): array
    {
        /*
         * One query per table rather than one per number. This used to run
         * fifteen round trips to build a card of counters — every one of them
         * a full scan of the same table the previous one had just scanned.
         *
         * `count(*) filter (where …)` is standard SQL and Postgres computes
         * every column in a single pass. The two tables that carry no
         * variants keep their plain count().
         */
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
     * Run several aggregates over one table in one query.
     *
     * The expressions are literals written above, never anything that came
     * from a request — the only interpolated values are two class constants —
     * so selectRaw is safe here and there is no bindable form of a `filter`
     * clause anyway.
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
     * The first-run checklist, in the order the work naturally happens.
     *
     * Each step is shown with its state rather than hidden once done, so the
     * owner can see what is left instead of watching items vanish. The whole
     * block is hidden by the page once every step is done.
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
            ->with(['page', 'mediaFile'])
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
