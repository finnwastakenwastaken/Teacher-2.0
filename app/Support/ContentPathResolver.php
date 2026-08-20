<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Topic;

/**
 * Resolves a public URL path like "natuurkunde/sterrenkunde/de-planeten" by
 * walking the topic tree one slug segment at a time. Deliberately ignores
 * is_hidden — hidden items still resolve by direct URL; hidden-ness only
 * removes them from navigation, the homepage grid and search (see
 * The technical reference, "Hidden ≠ deleted").
 */
class ContentPathResolver
{
    public static function resolve(string $path): Topic|Page|null
    {
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn (string $segment) => $segment !== ''
        ));

        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $topic = null;

        foreach ($segments as $index => $segment) {
            $isLast = $index === array_key_last($segments);

            $topic = Topic::query()->where('parent_id', $parentId)->where('slug', $segment)->first();

            if ($topic !== null) {
                $parentId = $topic->id;

                continue;
            }

            if ($isLast && $parentId !== null) {
                return Page::query()->where('topic_id', $parentId)->where('slug', $segment)->first();
            }

            return null;
        }

        return $topic;
    }
}
