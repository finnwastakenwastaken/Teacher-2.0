<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One previously published body of a page.
 *
 * Written only by App\Models\Page::writeContent(), which snapshots the body it
 * is about to replace. Nothing else may create one: a revision that did not
 * come from a publish would put an entry in the history the site never
 * actually served.
 *
 * `content` is fillable and `content_text` with it, unlike on Page — the two
 * are opposite cases. On a page those columns are live: everything derived
 * hangs off them, so leaving them fillable is one added validation rule away
 * from an unwhitelisted document reaching the public site. Here they are a
 * dead copy of a document that was already whitelisted on its way into
 * `pages.content`, read by exactly one screen behind `auth`, and re-sanitised
 * again by writeContent() on the way back out if it is ever restored.
 *
 * The columns below are for static analysis. Keep them in step with the
 * migration: a line without a column is a lie the analyser will believe.
 *
 * @property int $id
 * @property int $page_id
 * @property array<string, mixed>|null $content
 * @property string|null $content_text
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 */
#[Fillable(['page_id', 'content', 'content_text'])]
class PageRevision extends Model
{
    /**
     * How many published bodies a page keeps.
     *
     * The cap is the whole design, not a preference: every one of these rides
     * along in the `database.sql` of every backup archive. Enforced inside the
     * same transaction as the write that creates one, so the list cannot drift
     * above ten between two requests. See Page::writeContent().
     */
    public const KEEP = 10;

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
