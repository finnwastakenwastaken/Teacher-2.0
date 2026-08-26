<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Topic;
use App\Support\ContentVisibility;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * What a crawler is told about this site.
 *
 * `robots.txt` lives here rather than in `public/` because the only reliable
 * way a crawler discovers a sitemap is the `Sitemap:` line, and that line has
 * to carry an absolute URL. A static file cannot know the domain — behind the
 * tunnel it is whatever Cloudflare forwards — so the two are generated
 * together, from the request, and stay in step by construction.
 */
class SitemapController extends Controller
{
    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow:',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function show(): Response
    {
        $entries = [
            ['loc' => url('/'), 'lastmod' => null],
        ];

        foreach ($this->listable() as $node) {
            $entries[] = [
                'loc' => url('/'.$node->fullPath()),
                'lastmod' => $node->updated_at?->toAtomString(),
            ];
        }

        return response($this->render($entries))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Every topic and page a crawler may be told about.
     *
     * Eager-loaded up the tree because both `fullPath()` and the password
     * walk climb `parent`, and the tree is capped at depth 2 — so two levels
     * of eager loading is the whole of it, and this stays three queries no
     * matter how much content there is.
     *
     * @return Collection<int, Topic|Page>
     */
    private function listable(): Collection
    {
        $topics = Topic::query()->with('parent.parent')->orderBy('id')->get();
        $pages = Page::query()->with('topic.parent.parent')->orderBy('id')->get();

        return $topics->concat($pages)->filter(
            fn (Topic|Page $node) => $this->isListable($node)
        )->values();
    }

    /**
     * Whether this node belongs in a public sitemap. Deliberately does not
     * consult the request: a sitemap is a public document anything may fetch
     * and cache, so it asks the visitor-independent question ("guarded at
     * all?") rather than `AccessControl::allows()`, which would put every
     * protected path in the file whenever the owner loaded it while logged
     * in. Hidden must cover ancestors for the same reason.
     */
    private function isListable(Topic|Page $node): bool
    {
        // Lives in ContentVisibility because search asks the same question —
        // written separately once, the two rules diverged.
        return ContentVisibility::isPubliclyListable($node);
    }

    /**
     * @param  list<array{loc: string, lastmod: ?string}>  $entries
     */
    private function render(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->escape($entry['loc']).'</loc>';

            if ($entry['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$this->escape($entry['lastmod']).'</lastmod>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
