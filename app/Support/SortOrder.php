<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies a drag-and-drop reorder to a set of sibling records.
 *
 * Dragging deliberately **reorders only, never reparents**. Moving an item to
 * a different parent goes through the edit form, because that path already
 * handles what a move implies — the depth cap, sibling slug uniqueness, and
 * the 301 redirects a changed path has to leave behind. A drag that silently
 * did all of that would be the same feature with none of the safeguards.
 *
 * So this refuses any request whose records do not already share a parent.
 * That is not merely defensive: it is the line between the two features.
 */
class SortOrder
{
    /**
     * @param  Builder<covariant Model>  $query  every record that may be reordered
     * @param  list<int>  $ids  the ids in their new order
     * @param  string|null  $groupBy  the column siblings must agree on
     */
    public static function apply(Builder $query, array $ids, ?string $groupBy = null): void
    {
        if ($ids === []) {
            return;
        }

        $records = (clone $query)->whereIn('id', $ids)->get();

        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => __('admin.sort.unknown_group'),
            ]);
        }

        if ($groupBy !== null && $records->pluck($groupBy)->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'ids' => __('admin.sort.cross_group'),
            ]);
        }

        // One statement per row, but in a transaction: a half-applied order is
        // worse than none, and these lists are short enough that the round
        // trips do not matter.
        DB::transaction(function () use ($records, $ids) {
            foreach ($ids as $position => $id) {
                $records->firstWhere('id', $id)
                    ?->forceFill(['sort_order' => $position])
                    ->saveQuietly();
            }
        });
    }
}
