<?php

namespace App\Models;

use App\Exceptions\DependentRecordsExistException;
use App\Observers\TopicObserver;
use App\Support\PageContent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $depth
 * @property string $title
 * @property string $slug
 * @property string|null $icon
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_hidden
 * @property int|null $access_password_id
 * @property array<string, mixed>|null $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Topic|null $parent
 * @property-read Collection<int, Topic> $childTopics
 * @property-read Collection<int, Page> $pages
 */
#[Fillable(['parent_id', 'title', 'slug', 'icon', 'description', 'sort_order', 'is_hidden', 'access_password_id'])]
#[ObservedBy(TopicObserver::class)]
class Topic extends Model
{
    /**
     * The deepest a topic may sit: three levels, numbered from zero. The
     * Postgres trigger (2026_08_09_000003_create_topic_tree_integrity_triggers.php)
     * is what actually enforces this; this constant only lets form checks
     * turn its refusal into a friendly error first.
     */
    public const MAX_DEPTH = 2;

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'content' => 'array',
        ];
    }

    /**
     * Persist the topic's introduction.
     *
     * `content` is deliberately absent from #[Fillable]: this is the only
     * writer, so no future `update($request->validated())` can put an
     * unwhitelisted document in the column.
     *
     * Embeds are stripped rather than allowed: a file becomes publicly
     * reachable by walking from it to the *pages* showing it
     * (App\Support\MediaAccess), and a topic is not a page row — an embed
     * here would render for the owner and 403 for every student.
     *
     * @param  array<string, mixed>|null  $document
     */
    public function writeContent(?array $document): void
    {
        // Wrapped in a transaction for symmetry with Page::writeContent(),
        // even though both callers already wrap this too.
        DB::transaction(function () use ($document): void {
            $this->forceFill([
                'content' => PageContent::sanitiseWithoutEmbeds($document),
            ])->save();
        });
    }

    /** @return BelongsTo<Topic, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    /** @return HasMany<Topic, $this> */
    public function childTopics(): HasMany
    {
        return $this->hasMany(Topic::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    /**
     * The full slug path from the root down to and including this topic,
     * e.g. "natuurkunde/sterrenkunde". Used for both the public URL and
     * breadcrumb construction. Walks the persisted parent chain, so it
     * always reflects the topic's current (saved) position in the tree.
     */
    public function fullPath(): string
    {
        $segments = [$this->slug];
        $ancestor = $this->parent;

        while ($ancestor !== null) {
            array_unshift($segments, $ancestor->slug);
            $ancestor = $ancestor->parent;
        }

        return implode('/', $segments);
    }

    /**
     * Deletes are blocked, not cascaded, when they would orphan data — see
     * The technical reference's conventions. Throwing here (rather than in the controller
     * alone) also catches deletes from tinker, a seeder, or a future bulk
     * operation.
     */
    protected static function booted(): void
    {
        static::deleting(function (Topic $topic): void {
            if ($topic->childTopics()->exists()) {
                throw new DependentRecordsExistException(
                    __('admin.topics.has_children')
                );
            }

            if ($topic->pages()->exists()) {
                throw new DependentRecordsExistException(
                    __('admin.topics.has_pages')
                );
            }
        });
    }
}
