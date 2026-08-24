<?php

namespace App\Rules;

use App\Models\Page;
use App\Models\Topic;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Slugs must be unique among siblings across BOTH topics and pages under the
 * same parent topic (or, when $scopeId is null, among root topics). Mirrors
 * — but does not replace — the enforce_sibling_slug_uniqueness() Postgres
 * trigger, which is the authoritative, race-proof check. This rule exists
 * purely to turn that into a friendly per-field validation message instead
 * of a raw database exception in the common, non-racing case.
 */
class SiblingSlugIsUnique implements ValidationRule
{
    public function __construct(
        private readonly ?int $scopeId,
        private readonly ?string $excludingType = null,
        private readonly ?int $excludingId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $topicConflict = Topic::query()
            ->where('parent_id', $this->scopeId)
            ->where('slug', $value)
            ->when(
                $this->excludingType === Topic::class,
                fn ($query) => $query->where('id', '!=', $this->excludingId)
            )
            ->exists();

        $pageConflict = Page::query()
            ->where('topic_id', $this->scopeId)
            ->where('slug', $value)
            ->when(
                $this->excludingType === Page::class,
                fn ($query) => $query->where('id', '!=', $this->excludingId)
            )
            ->exists();

        if ($topicConflict || $pageConflict) {
            $fail(__('admin.fields.slug_taken'));
        }
    }
}
