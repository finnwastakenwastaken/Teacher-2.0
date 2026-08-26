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
 * `content`, `content_text`, `draft_content` and `draft_saved_at` are
 * deliberately absent from #[Fillable] (matching Topic): writeContent() and
 * writeDraft() are the only writers, both using forceFill, so a stray
 * `update($request->validated())` can never put an unwhitelisted document in
 * either column — the model where that matters most, since pages are the
 * only things that carry embeds. content_text is derived and feeds the
 * search vector, so it must never be client-supplied.
 *
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
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
 * @property array<string, mixed>|null $draft_content
 * @property CarbonImmutable|null $draft_saved_at
 * @property int|null $access_password_id
 * @property int|null $hero_image_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * `snippet` is not a column — SearchController selects it with ts_headline(),
 * so it only exists on rows that came back from a search, hence nullable.
 * @property string|null $snippet
 * @property-read Topic $topic
 * @property-read Image|null $heroImage
 * @property-read Collection<int, PageDownload> $downloads
 * @property-read Collection<int, PageMediaReference> $mediaReferences
 * @property-read Collection<int, PageRevision> $revisions
 */
#[Fillable(['topic_id', 'title', 'slug', 'icon', 'description', 'sort_order', 'is_hidden', 'hero_image_id', 'access_password_id'])]
#[ObservedBy(PageObserver::class)]
class Page extends Model
{
    /**
     * An unpublished concept never rides along in a serialised page.
     *
     * The admin editor is sent it deliberately, as its own `draft` prop, so
     * hiding it here costs nothing and removes a whole class of accident: any
     * future response that hands a Page to Inertia or to JSON would otherwise
     * ship a body the owner has explicitly not published. Attribute access
     * (promoteDraft, the editor payload) is unaffected — this only governs
     * toArray().
     *
     * @var list<string>
     */
    protected $hidden = ['draft_content'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'hero_image_id' => 'integer',
            'content' => 'array',
            'draft_content' => 'array',
            'draft_saved_at' => 'immutable_datetime',
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
     * The last ten published bodies, newest first.
     *
     * Ordered by id rather than by created_at: two publishes inside the same
     * second are ordinary — a restore is itself a publish, and the editor can
     * produce one immediately after another — and a timestamp cannot tell
     * those apart. The prune in writeContent() asks the same question the
     * same way, so what the owner sees and what survives are one ordering.
     *
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('id');
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
     * Persist a TipTap document and everything derived from it: the
     * whitelisted body (App\Support\PageContent), the flattened search text,
     * and the media reference rows that decide what is published. One
     * transaction, so a half-written page cannot leave a file published that
     * nothing shows.
     *
     * Publishing a body also ends any concept there was, in the same write.
     * That invariant lives here rather than in the callers because there is
     * no caller for which it is wrong — a promote, a save-and-publish and a
     * duplicated page all mean "this is the body now" — and because a caller
     * that forgot would leave the owner being offered a concept for ever,
     * with no way to tell it apart from unpublished work.
     *
     * And it is where the version history is written, for the same reason: a
     * publish is the only thing that changes what the site is serving, so it
     * is the only honest unit to keep a history of. An autosave is not one —
     * the debounce fires while the owner is still typing, and ten entries
     * would be ten seconds of one sentence.
     *
     * @param  array<string, mixed>|null  $document
     */
    public function writeContent(?array $document): void
    {
        DB::transaction(function () use ($document): void {
            $clean = PageContent::sanitise($document);

            $this->recordRevision();

            $this->forceFill([
                'content' => $clean,
                'content_text' => PageContent::toPlainText($clean),
                'draft_content' => null,
                'draft_saved_at' => null,
            ])->save();

            $this->syncMediaReferences($clean);
        });
    }

    /**
     * Keep the body that is about to be replaced, and drop the oldest.
     *
     * The *outgoing* body, deliberately: the one being written is on the page
     * row a moment later, and what somebody reaching for a history wants is
     * the version they just lost. It falls out of that choice that restoring
     * is itself a publish — it comes back through writeContent(), which
     * snapshots whatever was live at that moment — so the list only ever
     * grows and looking at an old version cannot cost you the current one.
     *
     * Read from the original attributes rather than from $this->content, so
     * a caller that had already assigned the new body in memory still
     * snapshots what the database holds.
     *
     * A page whose body is null has nothing worth going back to, so it is not
     * recorded. That is also what keeps a duplicated page's history empty:
     * App\Actions\DuplicatePage writes the copied body into a row that has
     * never had one. A copy is a new page — it has no past, and its first
     * publish starts its own.
     */
    private function recordRevision(): void
    {
        /** @var array<string, mixed>|null $outgoing */
        $outgoing = $this->getOriginal('content');

        if ($outgoing === null) {
            return;
        }

        $this->revisions()->create([
            'content' => $outgoing,
            // Not re-derived. It was flattened from this exact document when
            // the document was published, and deriving it a second time in a
            // second place is one more thing that can drift.
            'content_text' => $this->getOriginal('content_text'),
        ]);

        // Pruned here rather than on a schedule, and inside the transaction
        // that created the row, so the list can never be seen above the cap —
        // there is no window in which a request reads eleven.
        $keep = PageRevision::query()
            ->where('page_id', $this->id)
            ->orderByDesc('id')
            ->limit(PageRevision::KEEP)
            ->pluck('id');

        PageRevision::query()
            ->where('page_id', $this->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * Save a concept — a body written down but not published.
     *
     * This is the autosave, and what it must NOT do is the whole design.
     * writeContent() above rebuilds `page_media_references`, and those rows
     * are what make an embedded file fetchable by an anonymous visitor
     * (App\Support\MediaAccess::isPubliclyReachable). It also re-derives
     * `content_text`, which is one of the three columns the `search_vector`
     * trigger watches. Autosaving through it would therefore publish every
     * image in a half-written body — including one the owner pasted, thought
     * better of, and deleted a minute later, which would stay published until
     * the next real save — and put an unfinished page in the public search
     * box. Neither failure is visible from the admin panel.
     *
     * So this writes one column that nothing else in the application reads.
     * The document is still whitelisted on the way in: it comes from a
     * browser like every other document, and being unpublished is not the
     * same as being unconstrained. What it does not do is touch anything
     * derived.
     *
     * Two things it also avoids, both for the same reason — an autosave is
     * not an edit anyone asked for:
     *
     * - `updated_at` is left alone. SitemapController publishes it as
     *   <lastmod>, so touching it would tell every crawler that a page
     *   changed while the owner was only typing. Same rule as
     *   downloads_count.
     * - Model events are skipped. PageObserver records the 301 redirects a
     *   move leaves behind, and a draft write moves nothing.
     *
     * @param  array<string, mixed>|null  $document
     */
    public function writeDraft(?array $document): void
    {
        $this->forceFill([
            'draft_content' => PageContent::sanitise($document),
            'draft_saved_at' => CarbonImmutable::now(),
        ]);

        $this->timestamps = false;

        try {
            $this->saveQuietly();
        } finally {
            $this->timestamps = true;
        }
    }

    /**
     * Publish the stored concept, and stop it being a concept.
     *
     * The promote, and the only way a draft ever becomes content. It goes
     * through writeContent(), so the media references, the derived text and
     * the search vector are all rebuilt exactly once, at the moment somebody
     * chose to publish — which is what lets writeDraft() above be as cheap
     * and as inert as it is.
     *
     * Clearing the draft is writeContent()'s own doing, not a second step
     * here: see its doc block. By the time it returns there is no draft left,
     * which is why the document is taken out of the column first.
     *
     * Returns false when there was nothing to promote, so a caller can say
     * so rather than reporting a publish that did not happen.
     */
    public function promoteDraft(): bool
    {
        if (! $this->hasDraft()) {
            return false;
        }

        // Held in a local deliberately. writeContent() nulls the column as
        // part of publishing, so reading it inside the call would depend on
        // PHP evaluating the argument first — true, but not something the
        // next reader should have to know.
        $document = $this->draft_content;

        $this->writeContent($document);

        return true;
    }

    /**
     * Whether an unpublished concept exists.
     *
     * The timestamp is what answers this, not the document — `draft_content`
     * is legitimately null for a page the owner emptied but has not published
     * yet, and reading the document would make that concept indistinguishable
     * from no concept at all. It is also what stops "publish" silently doing
     * nothing when the thing being published is an empty page.
     */
    public function hasDraft(): bool
    {
        return $this->draft_saved_at !== null;
    }

    /**
     * Throw the concept away and leave the published body untouched.
     *
     * Falls out of the separation for free: the published body is a different
     * column, so there is nothing to undo and no derived row to rebuild.
     */
    public function discardDraft(): void
    {
        if (! $this->hasDraft()) {
            return;
        }

        $this->forceFill([
            'draft_content' => null,
            'draft_saved_at' => null,
        ]);

        $this->timestamps = false;

        try {
            $this->saveQuietly();
        } finally {
            $this->timestamps = true;
        }
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
