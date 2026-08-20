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
        return [
            'topics' => Topic::query()->count(),
            'hiddenTopics' => Topic::query()->where('is_hidden', true)->count(),
            'pages' => Page::query()->count(),
            'hiddenPages' => Page::query()->where('is_hidden', true)->count(),
            'emptyPages' => Page::query()->whereNull('content')->count(),
            'images' => Image::query()->count(),
            'documents' => MediaFile::query()->documents()->count(),
            'videos' => MediaFile::query()->videos()->count(),
            'mediaBytes' => (int) Image::query()->sum('size_bytes')
                + (int) MediaFile::query()->sum('size_bytes'),
            'downloads' => PageDownload::query()->count(),
            // The site's only counter, and an aggregate with no visitor data
            // attached to it — see the technical reference. Authenticated requests are not
            // counted, so this is what students actually took.
            'downloadsServed' => (int) PageDownload::query()->sum('downloads_count'),
            'levels' => EducationLevel::query()->count(),
            'passwords' => AccessPassword::query()->count(),
        ];
    }

    /**
     * The first-run checklist, in the order the work naturally happens.
     *
     * Each step is shown with its state rather than hidden once done, so the
     * owner can see what is left instead of watching items vanish. The whole
     * block is hidden by the page once every step is done.
     *
     * @param  array<string, int|bool>  $counts
     * @return list<array<string, mixed>>
     */
    private function nextSteps(array $counts): array
    {
        // A title the owner has never touched still resolves to APP_NAME.
        $named = SiteSettings::get('site_title') !== config('app.name');

        return [
            [
                'key' => 'branding',
                'title' => 'Geef de site een naam',
                'description' => 'Stel de naam, het logo en de favicon in.',
                'href' => route('admin.site-settings.edit'),
                'done' => $named,
            ],
            [
                'key' => 'topic',
                'title' => 'Maak je eerste onderwerp',
                'description' => 'Onderwerpen vormen het menu en de indeling van de site.',
                'href' => route('admin.topics.create'),
                'done' => $counts['topics'] > 0,
            ],
            [
                'key' => 'page',
                'title' => 'Maak je eerste pagina',
                'description' => 'Een pagina hangt onder een onderwerp en draagt de inhoud.',
                'href' => route('admin.pages.create'),
                'done' => $counts['pages'] > 0,
            ],
            [
                'key' => 'content',
                'title' => 'Schrijf de inhoud van een pagina',
                'description' => 'Tekst, afbeeldingen, video en YouTube-fragmenten.',
                'href' => route('admin.topics.index'),
                'done' => $counts['pages'] > 0 && $counts['emptyPages'] < $counts['pages'],
            ],
            [
                'key' => 'media',
                'title' => 'Upload lesmateriaal',
                'description' => 'Afbeeldingen, documenten en video in de mediabibliotheek.',
                'href' => route('admin.media.index'),
                'done' => $counts['images'] + $counts['documents'] + $counts['videos'] > 0,
            ],
            [
                'key' => 'download',
                'title' => 'Bied een download aan per niveau',
                'description' => 'Hetzelfde werkblad in een versie per leerweg.',
                'href' => route('admin.topics.index'),
                'done' => $counts['downloads'] > 0,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
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
     * @return list<array<string, mixed>>
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
                'page' => $download->page?->title,
                'count' => $download->downloads_count,
            ])
            ->all();
    }
}
