<?php

namespace App\Actions;

use App\Models\Page;
use App\Models\PageDownload;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * "Last year's worksheet page, but for this class."
 *
 * A page is more than its row: the body carries derived media references and
 * the downloads section carries level tags on the attachment rather than on
 * the file. Copying the row alone would produce a page that looks right in the
 * admin list and is empty when a student opens it, so the whole thing is
 * copied, in one transaction.
 */
class DuplicatePage
{
    public function handle(Page $page): Page
    {
        return DB::transaction(function () use ($page): Page {
            $copy = Page::query()->create([
                'topic_id' => $page->topic_id,
                'title' => $this->freeTitle($page),
                'slug' => $this->freeSlug($page),
                'icon' => $page->icon,
                'description' => $page->description,
                'hero_image_id' => $page->hero_image_id,
                'access_password_id' => $page->access_password_id,
                // A duplicate starts hidden: publishing it immediately puts
                // two identical pages in front of students, and the point of
                // duplicating is to change it first.
                'is_hidden' => true,
                'sort_order' => $this->endOfList($page->topic_id),
            ]);

            // Through writeContent(), not a column copy: it re-derives
            // content_text and rebuilds page_media_references, which is what
            // keeps every file the body shows published for the copy too.
            $copy->writeContent($page->content);

            $this->copyDownloads($page, $copy);

            return $copy;
        });
    }

    /**
     * The downloads section, level tags and all.
     *
     * `downloads_count` is deliberately not copied. The tally belongs to the
     * attachment that earned it; starting a fresh page at last year's number
     * would be a lie the owner cannot correct.
     */
    private function copyDownloads(Page $page, Page $copy): void
    {
        foreach ($page->downloads()->with('educationLevels')->get() as $download) {
            /** @var PageDownload $attachment */
            $attachment = $copy->downloads()->create([
                // Whichever of the two the original named, and only that one:
                // the CHECK constraint refuses an attachment carrying both,
                // so copying them as a pair is not merely untidy.
                'media_file_id' => $download->media_file_id,
                'image_id' => $download->image_id,
                'label' => $download->label,
                'sort_order' => $download->sort_order,
            ]);

            $attachment->educationLevels()->sync(
                $download->educationLevels->pluck('id')->all()
            );
        }
    }

    private function freeTitle(Page $page): string
    {
        $title = $page->title.' (kopie)';

        return mb_strlen($title) > 255 ? mb_substr($title, 0, 255) : $title;
    }

    /**
     * A slug no sibling is using.
     *
     * Sibling uniqueness spans topics *and* pages under the same parent and is
     * enforced by a Postgres trigger, so guessing wrong is an exception rather
     * than a warning — hence checking both tables here, and counting up until
     * something is free rather than assuming "-kopie" is.
     */
    private function freeSlug(Page $page): string
    {
        $base = mb_substr($page->slug, 0, 240).'-kopie';

        if (! $this->slugTaken($page->topic_id, $base)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $candidate = $base.'-'.$suffix;

            if (! $this->slugTaken($page->topic_id, $candidate)) {
                return $candidate;
            }
        }

        // Nine hundred and ninety-eight copies of one page is not a case worth
        // designing for, but silently overwriting one would be.
        return $base.'-'.now()->format('YmdHis');
    }

    private function slugTaken(int $topicId, string $slug): bool
    {
        return Page::query()->where('topic_id', $topicId)->where('slug', $slug)->exists()
            || Topic::query()->where('parent_id', $topicId)->where('slug', $slug)->exists();
    }

    private function endOfList(int $topicId): int
    {
        $last = Page::query()->where('topic_id', $topicId)->max('sort_order');

        return $last === null ? 0 : $last + 1;
    }
}
