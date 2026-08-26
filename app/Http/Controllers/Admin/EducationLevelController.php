<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DependentRecordsExistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEducationLevelRequest;
use App\Http\Requests\Admin\UpdateEducationLevelRequest;
use App\Models\EducationLevel;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managing the education tracks downloads can be tagged with.
 *
 * The list is the owner's to shape — see the migration for why it is seeded
 * rather than hardcoded.
 */
class EducationLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/levels/index', [
            'levels' => EducationLevel::query()
                ->withCount('pageDownloads')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'sort_order'])
                ->map(fn (EducationLevel $level) => [
                    // The id is here for merge_into; routes still bind on slug.
                    ...$level->only(['id', 'name', 'slug', 'sort_order']),
                    'downloadsCount' => $level->page_downloads_count,
                ]),
        ]);
    }

    /**
     * Persist a drag-and-drop reorder. Levels are a flat list, so there is no
     * grouping to check — every level is a sibling of every other.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ])['ids'];

        SortOrder::apply(EducationLevel::query(), $ids);

        return back();
    }

    public function store(StoreEducationLevelRequest $request): RedirectResponse
    {
        EducationLevel::query()->create($request->validated());

        return back()->with('status', __('admin.levels.created'));
    }

    public function update(UpdateEducationLevelRequest $request, EducationLevel $level): RedirectResponse
    {
        $level->update($request->validated());

        return back()->with('status', __('admin.levels.updated'));
    }

    /**
     * Delete a level, optionally merging its downloads into another one. A
     * level in use cannot simply be removed — that would strip the tag off
     * every download carrying it, which looks like a rendering bug rather
     * than the data loss it is.
     */
    public function destroy(Request $request, EducationLevel $level): RedirectResponse
    {
        $validated = $request->validate([
            'merge_into' => [
                'nullable', 'integer',
                Rule::exists('education_levels', 'id')->whereNot('id', $level->id),
            ],
        ], [
            'merge_into.exists' => __('admin.levels.merge_target_missing'),
        ]);

        try {
            DB::transaction(function () use ($level, $validated): void {
                if (($validated['merge_into'] ?? null) !== null) {
                    $this->mergeInto($level, (int) $validated['merge_into']);
                }

                $level->delete();
            });
        } catch (DependentRecordsExistException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('admin.levels.deleted'));
    }

    /**
     * Re-tag everything carrying $level with $targetId, then drop the old tag.
     *
     * syncWithoutDetaching rather than a bulk UPDATE on the pivot: a download
     * already tagged with both levels would violate the unique pair, and the
     * right outcome there is one row, not an error.
     */
    private function mergeInto(EducationLevel $level, int $targetId): void
    {
        foreach ($level->pageDownloads()->get() as $download) {
            $download->educationLevels()->syncWithoutDetaching([$targetId]);
            $download->educationLevels()->detach($level->id);
        }
    }
}
