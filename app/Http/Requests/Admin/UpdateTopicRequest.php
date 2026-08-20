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
     * sort_order is NOT NULL in the database (default 0) so it must never
     * reach validated() as null — an explicit null in the update payload
     * overrides the column default and throws.
     *
     * Order is set by dragging, so the form does not send this field at all.
     * Defaulting it to 0 would therefore throw the list's order away every
     * time the owner saved an unrelated edit; the current value is kept
     * instead. A topic that moves to a different parent joins the end of its
     * new siblings, because its old number means nothing there.
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

    public function rules(): array
    {
        /** @var Topic $topic */
        $topic = $this->route('topic');

        return [
            'parent_id' => [
                'nullable', 'integer', Rule::exists('topics', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($topic) {
                    if ($value !== null && (int) $value === $topic->id) {
                        $fail('Een onderwerp kan niet zijn eigen bovenliggende onderwerp zijn.');

                        return;
                    }

                    $parent = $value === null ? null : Topic::find($value);

                    if ($parent !== null && $parent->depth >= 2) {
                        $fail('Onderwerpen kunnen maximaal 3 niveaus diep zijn.');
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
            'content.type' => ['required_with:content', 'string'],
            'sort_order' => ['integer'],
            'access_password_id' => ['nullable', 'integer', Rule::exists('access_passwords', 'id')],
            'is_hidden' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vul een titel in.',
            'slug.required' => 'Vul een slug in.',
            'slug.regex' => 'De slug mag alleen kleine letters, cijfers en koppeltekens bevatten.',
            'parent_id.exists' => 'Het gekozen bovenliggende onderwerp bestaat niet.',
        ];
    }
}
