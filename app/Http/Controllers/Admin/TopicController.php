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
use App\Support\TreeConstraintViolation;
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

                // Only when the key was actually sent. writeContent(null)
                // stores an empty introduction, so an unconditional call
                // makes any request that omits the field — a script, a
                // future partial form, a retry that drops it — erase what is
                // there. Absence and emptiness are not the same intent.
                if ($request->has('content')) {
                    $topic->writeContent($request->input('content'));
                }
            });
        } catch (QueryException $e) {
            return $this->refuse($e);
        }

        return to_route('admin.topics.index')->with('status', __('admin.topics.created'));
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

                // See store(): absence is not the same as an empty
                // introduction, and this runs on every topic edit.
                if ($request->has('content')) {
                    $topic->writeContent($request->input('content'));
                }
            });
        } catch (QueryException $e) {
            return $this->refuse($e);
        }

        return to_route('admin.topics.index')->with('status', __('admin.topics.updated'));
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        try {
            $topic->delete();
            // Thrown from a `deleting` model event, invisible to PHPStan from
            // here, so it flags this catch as dead. It is not: removing it
            // turns "still in use" into a 500.
            // @phpstan-ignore catch.neverThrown
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('admin.topics.index')->with('status', __('admin.topics.deleted'));
    }

    /**
     * Topics that could validly become a parent: anything at depth 0 or 1
     * (depth 2 is already the deepest level), excluding $topic itself when
     * editing.
     *
     * @return Collection<int, Topic>
     */
    private function possibleParents(?Topic $topic = null): Collection
    {
        return Topic::query()
            ->where('depth', '<', Topic::MAX_DEPTH)
            ->when($topic !== null, fn ($query) => $query->where('id', '!=', $topic->id))
            ->orderBy('title')
            ->get(['id', 'title', 'depth']);
    }

    /**
     * The whole tree, nested, for the sortable admin list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function tree(): Collection
    {
        return $this->branch(
            null,
            Topic::query()->orderBy('sort_order')->get(),
            // `draft_saved_at` is selected, not `draft_content`: the concept
            // badge needs to know a concept exists, and loading a jsonb body
            // per row to answer a boolean is the expensive way to be told.
            // Leaving the column out entirely would be worse than expensive —
            // an unselected attribute reads as null, so hasDraft() would
            // answer "no concept" for every page and look correct.
            Page::query()->orderBy('sort_order')
                ->get(['id', 'topic_id', 'title', 'slug', 'is_hidden', 'draft_saved_at']),
        );
    }

    /**
     * One level of the tree, recursing into its children. A named method
     * rather than a recursive closure — PHPStan can't settle on a return type
     * for a self-calling closure. Both collections are passed down so the
     * whole tree costs two queries.
     *
     * @param  Collection<int, Topic>  $topics
     * @param  Collection<int, Page>  $pages
     * @return Collection<int, array<string, mixed>>
     */
    private function branch(?int $parentId, Collection $topics, Collection $pages): Collection
    {
        // Collection's value type is invariant, so a collection of a specific
        // array shape is not a collection of array<string, mixed> as far as
        // the analyser is concerned, even though every value fits.
        // @phpstan-ignore return.type
        return $topics
            ->where('parent_id', $parentId)
            ->map(fn (Topic $topic): array => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'icon' => $topic->icon,
                'is_hidden' => $topic->is_hidden,
                'depth' => $topic->depth,
                'children' => $this->branch($topic->id, $topics, $pages),
                // Shaped by hand rather than serialised: `has_draft` is a
                // question, not a column, and sending draft_saved_at raw
                // would put a timestamp on screen where a badge belongs.
                'pages' => $pages->where('topic_id', $topic->id)
                    ->map(fn (Page $page): array => [
                        'id' => $page->id,
                        'title' => $page->title,
                        'slug' => $page->slug,
                        'is_hidden' => $page->is_hidden,
                        'has_draft' => $page->hasDraft(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    /**
     * Show the owner what they can fix — or let a real failure be a failure.
     * Only the two violations the triggers raise deliberately get a friendly
     * slug-field message; anything else is reported and rethrown, since a
     * stack trace beats a wrong hint on a field that's fine.
     */
    private function refuse(QueryException $e): RedirectResponse
    {
        $message = TreeConstraintViolation::message($e);

        report($e);

        if ($message === null) {
            throw $e;
        }

        return back()->withErrors(['slug' => $message])->withInput();
    }
}
