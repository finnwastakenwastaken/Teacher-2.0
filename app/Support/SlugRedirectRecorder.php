<?php

namespace App\Support;

use App\Models\Page;
use App\Models\SlugRedirect;
use App\Models\Topic;

/**
 * Writes a slug_redirects row for the OLD full path of a topic or page right
 * before a slug or parent/topic change is persisted, so links already shared
 * with students keep working as a 301 instead of breaking (see the technical reference,
 * "Slug stability"). The redirect target is stored as a polymorphic
 * reference, not a frozen destination path — resolving it always walks the
 * CURRENT tree, so a redirect stays correct even if the item is renamed or
 * moved again later.
 *
 * Callers must run this — via the model observers below — inside the same
 * database transaction as the save it precedes. If the save itself fails
 * (e.g. the depth-cap trigger rejects the move), the redirect row must roll
 * back with it; otherwise it would point at a move that never happened.
 */
class SlugRedirectRecorder
{
    public static function recordTopicMove(Topic $topic): void
    {
        $oldSlug = $topic->getOriginal('slug');
        $oldParentId = $topic->getOriginal('parent_id');
        $oldPath = self::joinPath(self::ancestorSlugs($oldParentId), $oldSlug);

        self::record($oldPath, $topic);
        self::recordDescendants($topic, $oldPath);
    }

    public static function recordPageMove(Page $page): void
    {
        $oldSlug = $page->getOriginal('slug');
        $oldTopic = Topic::find($page->getOriginal('topic_id'));

        if ($oldTopic === null) {
            return;
        }

        self::record($oldTopic->fullPath().'/'.$oldSlug, $page);
    }

    private static function recordDescendants(Topic $topic, string $oldParentPath): void
    {
        foreach ($topic->childTopics as $child) {
            $childOldPath = $oldParentPath.'/'.$child->slug;
            self::record($childOldPath, $child);
            self::recordDescendants($child, $childOldPath);
        }

        foreach ($topic->pages as $page) {
            self::record($oldParentPath.'/'.$page->slug, $page);
        }
    }

    /**
     * Deliberately does not compare $oldPath against $model->fullPath() to
     * skip a "no-op" — for a descendant, fullPath() would resolve the moved
     * ancestor via a fresh query, which still returns that ancestor's OLD
     * row (its own UPDATE hasn't been committed yet at this point in the
     * observer chain), making old and "current" paths look identical even
     * though the move is genuinely happening. Callers only reach here after
     * already confirming a real slug/parent change, so recording
     * unconditionally is correct; firstOrCreate keeps this idempotent if the
     * same old path is renamed through again later.
     */
    private static function record(string $oldPath, Topic|Page $model): void
    {
        SlugRedirect::query()->firstOrCreate(
            ['from_path' => $oldPath],
            ['redirectable_type' => $model::class, 'redirectable_id' => $model->id]
        );
    }

    private static function ancestorSlugs(?int $topicId): array
    {
        if ($topicId === null) {
            return [];
        }

        $topic = Topic::find($topicId);

        return $topic === null ? [] : explode('/', $topic->fullPath());
    }

    private static function joinPath(array $ancestorSlugs, string $slug): string
    {
        return implode('/', [...$ancestorSlugs, $slug]);
    }
}
