<?php

namespace App\Models;

use App\Exceptions\DependentRecordsExistException;
use App\Observers\TopicObserver;
use App\Support\PageContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'title', 'slug', 'icon', 'description', 'sort_order', 'is_hidden', 'access_password_id'])]
#[ObservedBy(TopicObserver::class)]
class Topic extends Model
{
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
     * unwhitelisted document in the column by adding one validation rule.
     *
     * Embeds are stripped rather than allowed. A file becomes publicly
     * reachable by walking from it to the *pages* showing it
     * (App\Support\MediaAccess) and a topic is not a page row, so an embed
     * here would render for the owner and 403 for every student — the exact
     * trap the homepage introduction has. Text, links and lists are what an
     * introduction needs; a lesson belongs on a page.
     *
     * @param  array<string, mixed>|null  $document
     */
    public function writeContent(?array $document): void
    {
        $this->forceFill([
            'content' => PageContent::sanitiseWithoutEmbeds($document),
        ])->save();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    public function childTopics(): HasMany
    {
        return $this->hasMany(Topic::class, 'parent_id')->orderBy('sort_order');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class)->orderBy('sort_order');
    }

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
                    'Dit onderwerp heeft nog subonderwerpen. Verplaats of verwijder deze eerst.'
                );
            }

            if ($topic->pages()->exists()) {
                throw new DependentRecordsExistException(
                    "Dit onderwerp heeft nog pagina's. Verplaats of verwijder deze eerst."
                );
            }
        });
    }
}
