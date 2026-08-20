<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DependentRecordsExistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTopicRequest;
use App\Http\Requests\Admin\UpdateTopicRequest;
use App\Models\AccessPassword;
use App\Models\Page;
use App\Models\Topic;
use App\Support\IconCatalogue;
use App\Support\SortOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/topics/index', [
            'tree' => $this->tree(),
            // Only topic icons: the tree draws icons for topics, not for the
            // page rows beneath them.
            'icons' => IconCatalogue::resolve(Topic::query()->pluck('icon')),
        ]);
    }

    /**
     * Persist a drag-and-drop reorder of sibling topics.
     *
     * Reordering only — see App\Support\SortOrder for why dragging must not
     * be able to reparent.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        SortOrder::apply(Topic::query(), $ids, 'parent_id');

        return back();
    }

    public function create(): Response
    {
        return Inertia::render('admin/topics/create', [
            'possibleParents' => $this->possibleParents(),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreTopicRequest $request): RedirectResponse
    {
        try {
            // Two writes, so one transaction: a topic must never exist for a
            // moment with an introduction that failed to save.
            DB::transaction(function () use ($request): void {
                $topic = Topic::query()->create($request->validated());

                $topic->writeContent($request->input('content'));
            });
        } catch (QueryException $e) {
            return back()->withErrors(['slug' => $this->friendlyMessage($e)])->withInput();
        }

        return to_route('admin.topics.index')->with('status', 'Onderwerp aangemaakt.');
    }

    public function edit(Topic $topic): Response
    {
        return Inertia::render('admin/topics/edit', [
            'topic' => $topic,
            // Geometry for the icon already chosen, so the picker can draw it
            // without asking the server a second time.
            'iconData' => IconCatalogue::resolve([$topic->icon])[$topic->icon] ?? null,
            'possibleParents' => $this->possibleParents($topic),
            'passwords' => AccessPassword::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateTopicRequest $request, Topic $topic): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $topic): void {
                $topic->update($request->validated());

                $topic->writeContent($request->input('content'));
            });
        } catch (QueryException $e) {
            return back()->withErrors(['slug' => $this->friendlyMessage($e)])->withInput();
        }

        return to_route('admin.topics.index')->with('status', 'Onderwerp bijgewerkt.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        try {
            $topic->delete();
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('admin.topics.index')->with('status', 'Onderwerp verwijderd.');
    }

    /**
     * Topics that could validly become a parent: anything at depth 0 or 1
     * (depth 2 is already the deepest level), excluding $topic itself when
     * editing.
     */
    private function possibleParents(?Topic $topic = null): Collection
    {
        return Topic::query()
            ->where('depth', '<', 2)
            ->when($topic !== null, fn ($query) => $query->where('id', '!=', $topic->id))
            ->orderBy('title')
            ->get(['id', 'title', 'depth']);
    }

    private function tree(): Collection
    {
        $topics = Topic::query()->orderBy('sort_order')->get();
        $pages = Page::query()->orderBy('sort_order')->get(['id', 'topic_id', 'title', 'slug', 'is_hidden']);

        $build = function (?int $parentId) use (&$build, $topics, $pages): Collection {
            return $topics->where('parent_id', $parentId)->map(fn (Topic $topic) => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'icon' => $topic->icon,
                'is_hidden' => $topic->is_hidden,
                'depth' => $topic->depth,
                'children' => $build($topic->id),
                'pages' => $pages->where('topic_id', $topic->id)->values(),
            ])->values();
        };

        return $build(null);
    }

    private function friendlyMessage(QueryException $e): string
    {
        return str_contains($e->getMessage(), 'niveaus diep')
            ? 'Onderwerpen kunnen maximaal 3 niveaus diep zijn.'
            : 'Deze wijziging kon niet worden opgeslagen.';
    }
}
