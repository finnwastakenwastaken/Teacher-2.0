<?php

namespace App\Models;

use App\Observers\PageObserver;
use App\Support\PageContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['topic_id', 'title', 'slug', 'icon', 'description', 'sort_order', 'is_hidden', 'hero_image_id', 'content', 'content_text', 'access_password_id'])]
#[ObservedBy(PageObserver::class)]
class Page extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'hero_image_id' => 'integer',
            'content' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function mediaReferences(): HasMany
    {
        return $this->hasMany(PageMediaReference::class);
    }

    /**
     * The banner across the top of the page, above the title.
     *
     * Not part of the body, so it is not in `page_media_references` and does
     * not get rebuilt when the body is saved. MediaAccess resolves it
     * through this page like any other image the page shows.
     */
    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'hero_image_id');
    }

    /**
     * The downloads section: files offered at the bottom of the page, each
     * tagged with the tracks it is meant for.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(PageDownload::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Persist a TipTap document, together with everything derived from it.
     *
     * The three derived values must never disagree with the body they came
     * from, so nothing here is accepted from the client: the document is
     * whitelisted (App\Support\PageContent), the plain-text copy that feeds
     * search is flattened from the result, and the media reference rows —
     * which decide both what is published and what may be deleted — are
     * rebuilt from it. One transaction, so a half-written page cannot leave
     * a file published that nothing actually shows.
     */
    public function writeContent(?array $document): void
    {
        DB::transaction(function () use ($document): void {
            $clean = PageContent::sanitise($document);

            $this->forceFill([
                'content' => $clean,
                'content_text' => PageContent::toPlainText($clean),
            ])->save();

            $this->syncMediaReferences($clean);
        });
    }

    private function syncMediaReferences(?array $document): void
    {
        $referenced = PageContent::references($document);

        $rows = collect();

        if ($referenced['images'] !== []) {
            $rows = $rows->concat(
                Image::query()->whereIn('ulid', $referenced['images'])->pluck('id')
                    ->map(fn (int $id) => ['referenceable_type' => Image::class, 'referenceable_id' => $id])
            );
        }

        if ($referenced['files'] !== []) {
            $rows = $rows->concat(
                MediaFile::query()->whereIn('ulid', $referenced['files'])->pluck('id')
                    ->map(fn (int $id) => ['referenceable_type' => MediaFile::class, 'referenceable_id' => $id])
            );
        }

        // Rebuilt wholesale rather than diffed. The set is small, and a
        // replace cannot leave a stale row behind the way a partial update
        // can — a stale row would keep a file published after the embed that
        // justified it was deleted.
        $this->mediaReferences()->delete();

        $this->mediaReferences()->createMany($rows->all());
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    /**
     * The full slug path from the root down through this page's topic,
     * e.g. "natuurkunde/sterrenkunde/de-planeten".
     */
    public function fullPath(): string
    {
        return $this->topic->fullPath().'/'.$this->slug;
    }
}
