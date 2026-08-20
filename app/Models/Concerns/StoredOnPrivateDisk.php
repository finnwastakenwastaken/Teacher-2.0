<?php

namespace App\Models\Concerns;

use App\Exceptions\DependentRecordsExistException;
use App\Models\PageMediaReference;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared behaviour for the two media libraries (images and media files).
 *
 * Both live on the `local` (private) disk, are addressed publicly by ULID
 * rather than by auto-increment id, block their own deletion while something
 * still points at them, and clean up their bytes once a delete does go
 * through.
 */
trait StoredOnPrivateDisk
{
    public static function bootStoredOnPrivateDisk(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });

        static::deleting(function (self $model): void {
            $dependents = $model->dependents();

            if ($dependents !== []) {
                throw new DependentRecordsExistException($model->dependencyMessage($dependents));
            }
        });

        static::deleted(function (self $model): void {
            // After commit, not during: a delete that rolls back must not
            // leave the row intact but the bytes gone. Outside a transaction
            // this runs immediately.
            $path = $model->path;

            DB::afterCommit(function () use ($path): void {
                Storage::disk(config('media.disk'))->delete($path);
            });
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Pages whose body embeds this file, via App\Support\PageContent.
     */
    public function pageReferences(): MorphMany
    {
        return $this->morphMany(PageMediaReference::class, 'referenceable');
    }

    /**
     * Everything that would be orphaned by deleting this file.
     *
     * Deletes block and report rather than cascading (the technical reference),
     * so this is the single place each library answers "is it still in use".
     *
     * @return array<string, list<string>> Human label => page titles using it.
     */
    public function dependents(): array
    {
        $pages = $this->pageReferences()
            ->with('page:id,title')
            ->get()
            ->map(fn (PageMediaReference $reference) => $reference->page?->title)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $dependents = $pages === [] ? [] : ['Gebruikt op' => $pages];

        return [...$dependents, ...$this->extraDependents()];
    }

    /**
     * Usages beyond page embeds, for libraries that have them.
     *
     * Images are only ever embedded, so the default is empty. Media files are
     * additionally offered as level-tagged downloads and override this — a
     * file nothing embeds but a page still offers must not look deletable.
     *
     * @return array<string, list<string>>
     */
    protected function extraDependents(): array
    {
        return [];
    }

    /**
     * @param  array<string, list<string>>  $dependents
     */
    protected function dependencyMessage(array $dependents): string
    {
        $parts = [];

        foreach ($dependents as $label => $users) {
            $parts[] = $label.': '.implode(', ', $users);
        }

        return 'Dit bestand is nog in gebruik en kan niet worden verwijderd. '
            .implode(' — ', $parts);
    }
}
