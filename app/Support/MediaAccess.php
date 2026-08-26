<?php

namespace App\Support;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The single authorisation decision for every byte of uploaded media — what
 * The technical reference calls the "no side door" guarantee: a file on a hidden or
 * password-protected page
 * must not be fetchable by guessing its URL. Every path that serves media
 * goes through allows() first; there is no second route, no public disk, no
 * signed-URL escape hatch (see `local` disk notes in config/filesystems.php).
 * Default answer is NO — fail-closed, because fail-open means a leak nobody
 * notices, while fail-closed is just a broken download someone reports.
 */
class MediaAccess
{
    public static function allows(Image|MediaFile $file, Request $request): bool
    {
        // Any authenticated user is the admin (one account, no registration —
        // §3.1/§3.2), who must be able to preview unplaced library files too.
        if ($request->user() !== null) {
            return true;
        }

        return self::isPubliclyReachable($file, $request);
    }

    /**
     * Whether an anonymous visitor may see this file: published (some page
     * embeds it or offers it as a download) AND that page is one this
     * visitor may open. Until something points at it, a file is private
     * regardless of its URL.
     *
     * Embeds (`page_media_references`, rebuilt on every body save) and
     * download attachments (authored rows) are checked separately — folding
     * downloads into the derived table would delete them on the next body
     * save. ANY reachable page is enough: a file already public via one page
     * cannot be made private via another, which is why passwords guard
     * *pages*, not files. Hidden pages count as reachable — hidden means
     * "not in menus/search", not "secret" (see the technical reference, "Hidden ≠
     * deleted"); excluding their media would render a broken preview instead
     * of a working one. Secrecy is what passwords are for.
     */
    private static function isPubliclyReachable(Image|MediaFile $file, Request $request): bool
    {
        // Branding (logo/favicon/homepage banner) is the one media category
        // not reached through a page — the logo renders on the login screen
        // itself. Deliberate third case, as narrow as the others: public only
        // while a branding setting points at it. Never widen to "any image".
        if ($file instanceof Image && in_array($file->id, SiteSettings::brandingImageIds(), true)) {
            return true;
        }

        foreach (self::pagesShowing($file) as $page) {
            if (AccessControl::allows($page, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every page that puts this file in front of a visitor.
     *
     * Eager-loads the topic chain because resolving a page's effective
     * password walks up to its nearest protected ancestor.
     *
     * @return Collection<int, Page>
     */
    private static function pagesShowing(Image|MediaFile $file)
    {
        $pageIds = $file->pageReferences()->pluck('page_id');

        // Either library can be offered in a page's downloads section — a
        // worksheet as a PDF, a poster or a scanned handout as an image, and
        // the same picture can legitimately be both embedded and offered.
        // Both arms land here rather than in a case of their own in
        // isPubliclyReachable(), so an image handed out on a protected page
        // inherits that page's password through the same AccessControl walk
        // as everything else on it, with no second rule to keep in step.
        $pageIds = $pageIds->concat($file->pageDownloads()->pluck('page_id'));

        // A hero image is shown by exactly one page and is subject to that
        // page's password like anything else on it, so it resolves through
        // the same walk rather than getting a case of its own.
        if ($file instanceof Image) {
            $pageIds = $pageIds->concat(
                Page::query()->where('hero_image_id', $file->id)->pluck('id')
            );
        }

        if ($pageIds->isEmpty()) {
            return collect();
        }

        return Page::query()
            ->whereIn('id', $pageIds->unique()->all())
            ->with('topic.parent.parent')
            ->get();
    }
}
