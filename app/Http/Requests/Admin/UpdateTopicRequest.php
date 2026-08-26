<?php

namespace App\Http\Requests\Admin;

use App\Models\Topic;
use App\Rules\SiblingSlugIsUnique;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * sort_order is NOT NULL, so an explicit null in the update payload would
     * throw. Order is set by dragging and the form never sends this field, so
     * the current value is kept rather than defaulted to 0 (which would
     * reshuffle the list on every unrelated edit); a topic moved to a
     * different parent joins the end of its new siblings instead.
     */
    protected function prepareForValidation(): void
    {
        /** @var Topic $topic */
        $topic = $this->route('topic');

        $parentId = $this->input('parent_id') ?: null;

        $this->merge([
            // The select submits an empty string for "geen wachtwoord"; the
            // column is a nullable FK, so that has to become a real null.
            'access_password_id' => $this->input('access_password_id') === '' ? null : $this->input('access_password_id'),
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? $this->keptOrEndOfList($topic, $parentId)
                : $this->input('sort_order'),
        ]);
    }

    private function keptOrEndOfList(Topic $topic, ?int $parentId): int
    {
        if ((int) $parentId === (int) $topic->parent_id) {
            return $topic->sort_order;
        }

        $last = Topic::query()->where('parent_id', $parentId)->max('sort_order');

        return $last === null ? 0 : $last + 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Topic $topic */
        $topic = $this->route('topic');

        return [
            'parent_id' => [
                'nullable', 'integer', Rule::exists('topics', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($topic) {
                    if ($value !== null && (int) $value === $topic->id) {
                        $fail(__('admin.topics.own_parent'));

                        return;
                    }

                    $parent = $value === null ? null : Topic::find((int) $value);

                    if ($parent === null) {
                        return;
                    }

                    // Without this check, moving a topic under its own
                    // descendant is only caught by accident (a depth-cap
                    // trigger error), and in some shapes not caught at all —
                    // the branch would detach from the tree entirely.
                    if ($this->isDescendant($parent, $topic)) {
                        $fail(__('admin.topics.own_descendant'));

                        return;
                    }

                    if ($parent->depth >= Topic::MAX_DEPTH) {
                        $fail(__('admin.topics.max_depth'));
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                new SiblingSlugIsUnique(
                    $this->input('parent_id') !== null ? (int) $this->input('parent_id') : null,
                    Topic::class,
                    $topic->id,
                ),
            ],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            // Shape only, so a malformed body is a validation error rather
            // than a TypeError. The document itself is whitelisted by
            // Topic::writeContent(); `content` is not fillable, so it is
            // discarded on the way through create()/update() and reaches the
            // column through that writer alone.
            'content' => ['nullable', 'array'],
            'content.type' => ['required_with:content', 'string', 'in:doc'],
            'sort_order' => ['integer'],
            'access_password_id' => ['nullable', 'integer', Rule::exists('access_passwords', 'id')],
            'is_hidden' => ['boolean'],
        ];
    }

    /**
     * Whether $candidate sits anywhere below $ancestor. Walks upward from the
     * candidate, at most two lookups given the three-level cap. The loop
     * counter is a backstop against a corrupted tree hanging the request.
     */
    private function isDescendant(Topic $candidate, Topic $ancestor): bool
    {
        $node = $candidate;

        for ($steps = 0; $steps <= Topic::MAX_DEPTH; $steps++) {
            if ($node->id === $ancestor->id) {
                return true;
            }

            if ($node->parent_id === null) {
                return false;
            }

            $parent = Topic::find($node->parent_id);

            if ($parent === null) {
                return false;
            }

            $node = $parent;
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'title.required' => __('admin.fields.title_required'),
            'slug.required' => __('admin.fields.slug_required'),
            'slug.regex' => __('admin.fields.slug_format'),
            'content.type.in' => __('admin.topics.intro_unreadable'),
            'parent_id.exists' => __('admin.topics.parent_missing'),
        ];
    }
}
