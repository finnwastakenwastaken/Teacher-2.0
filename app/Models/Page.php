<?php

namespace App\Models;

use App\Observers\PageObserver;
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
 * `content` and `content_text` are deliberately absent from #[Fillable],
 * matching Topic. writeContent() is the only writer of either and uses
 * forceFill, so nothing legitimate needs them there — while leaving them
 * fillable meant one added validation rule away from
 * `update($request->validated())` putting an unwhitelisted document straight
 * into the column, on the model where the whitelist actually matters because
 * pages are the only things that carry embeds. content_text is derived and
 * feeds the search vector: client-supplied it would describe something other
 * than what is stored.
 *
 * The columns below are for static analysis. Eloquent resolves them at
 * runtime, so nothing here changes behaviour — but without them every
 * `$page->slug` is an undefined property to PHPStan, and a genuine typo is
 * indistinguishable from the hundred false ones. Keep in step with the
 * migrations; a column added without a line here is invisible to the analyser
 * and a line here without a column is a lie it will believe.
 *
 * @property int $id
 * @property int $topic_id
 * @property string $title
 * @property string $slug
 * @property string|null $icon
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_hidden
 * @property array<string, mixed>|null $content
 * @property string|null $content_text
 * @property int|null $access_password_id
 * @property int|null $hero_image_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * `snippet` is not a column. SearchController selects it with ts_headline(),
 * so it exists on the rows that came back from a search and nowhere else —
 * hence nullable rather than string.
 * @property string|null $snippet
 * @property-read Topic $topic
 * @property-read Image|null $heroImage
 * @property-read Collection<int, PageDownload> $downloads
 * @property-read Collection<int, PageMediaReference> $mediaReferences
 */
#[Fillable(['topic_id', 'title', 'slug', 'icon', 'description', 'sort_order', 'is_hidden', 'hero_image_id', 'access_password_id'])]
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

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /** @return HasMany<PageMediaReference, $this> */
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
     *
     * @return BelongsTo<Image, $this>
     */
    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'hero_image_id');
    }

    /**
     * The downloads section: files offered at the bottom of the page, each
     * tagged with the tracks it is meant for.
     *
     * @return HasMany<PageDownload, $this>
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
     *
     * @param  array<string, mixed>|null  $document
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

    /**
     * @param  array<string, mixed>|null  $document
     */
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
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
