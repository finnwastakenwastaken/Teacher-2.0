<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\AccessControl;
use App\Support\ContentVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * How many rows to pull before access filtering. Filtering has to happen
     * in PHP because whether a page is readable depends on the visitor's
     * unlock cookies and on walking the topic tree, neither of which belongs
     * in the query. The site is tens to hundreds of pages, so over-fetching
     * a little and trimming is cheaper than any of the alternatives.
     */
    private const CANDIDATES = 60;

    private const RESULTS = 20;

    public function show(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('content/search', [
            'query' => $query,
            'results' => $query === '' ? [] : $this->search($query, $request),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $query, Request $request): array
    {
        // The query has to be parsed with the same configuration the vector
        // was built with, or the stemmer disagrees with the index and a page
        // that plainly contains the word does not come back. Read through
        // content_search_config() — the function the trigger uses — rather
        // than interpolating a PHP value, so the two cannot drift and no
        // configuration name is ever built from a string in this file.
        //
        // websearch_to_tsquery parses what people actually type — quoted
        // phrases, OR, a leading minus — and, unlike to_tsquery, never throws
        // on punctuation. That matters when the input is a search box.
        $candidates = Page::query()
            ->select('pages.*')
            // StartSel/StopSel are emptied deliberately: ts_headline wraps
            // matches in <b> by default, and this snippet is rendered as
            // text, never as markup — see the note in rich-text.tsx.
            ->selectRaw(
                "ts_headline(content_search_config(), coalesce(content_text, ''),
                    websearch_to_tsquery(content_search_config(), ?),
                    'StartSel=\"\", StopSel=\"\", MaxWords=30, MinWords=15, MaxFragments=1') as snippet",
                [$query]
            )
            ->whereRaw('search_vector @@ websearch_to_tsquery(content_search_config(), ?)', [$query])
            // Hidden pages are drafts and retired material: reachable by
            // direct link, never surfaced.
            ->where('is_hidden', false)
            ->orderByRaw('ts_rank(search_vector, websearch_to_tsquery(content_search_config(), ?)) desc', [$query])
            ->orderBy('title')
            ->with('topic.parent.parent')
            ->limit(self::CANDIDATES)
            ->get();

        $results = [];

        foreach ($candidates as $page) {
            // Hidden has to cover ancestors, not just the page. The query
            // above filters pages.is_hidden, which on its own let every page
            // under a hidden topic stay fully searchable — title and snippet
            // included — so hiding a retired subject did not retire it. The
            // sitemap always walked the chain; this is the same rule, now
            // from the same function so the two cannot drift again.
            if (! ContentVisibility::isDiscoverable($page)) {
                continue;
            }

            // A protected page must not appear to someone who cannot open
            // it — the title and snippet would leak exactly what the
            // password is there to withhold. It does appear once they have
            // unlocked it, which is the whole reason they entered it. This
            // is the visitor-dependent question, deliberately not the
            // sitemap's visitor-independent one.
            if (! AccessControl::allows($page, $request)) {
                continue;
            }

            $results[] = [
                'id' => $page->id,
                'title' => $page->title,
                'description' => $page->description,
                'href' => '/'.$page->fullPath(),
                'snippet' => $page->snippet === '' ? null : $page->snippet,
                'topic' => $page->topic->title,
            ];

            if (count($results) >= self::RESULTS) {
                break;
            }
        }

        return $results;
    }
}
