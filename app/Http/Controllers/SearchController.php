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
     * How many rows to pull before access filtering, which has to happen in
     * PHP — readability depends on unlock cookies and walking the topic
     * tree, neither of which belongs in the query.
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
        // Parsed with content_search_config() — the same function the trigger
        // uses — or the stemmer disagrees with the index and a page that
        // plainly contains the word doesn't come back. websearch_to_tsquery,
        // unlike to_tsquery, never throws on punctuation from a search box.
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
            // Hidden must cover ancestors too — a page under a hidden topic
            // is otherwise still fully searchable. Shared with the sitemap's
            // rule via the same function, so the two cannot drift.
            if (! ContentVisibility::isDiscoverable($page)) {
                continue;
            }

            // A protected page must not appear to a visitor who can't open
            // it — title and snippet would leak what the password withholds.
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
