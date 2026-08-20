<?php

namespace App\Http\Requests\Admin;

use App\Models\Topic;
use App\Rules\SiblingSlugIsUnique;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the 'auth' middleware; there is only ever one admin, so
        // no per-resource policy is needed on top of that.
        return true;
    }

    /**
     * sort_order is NOT NULL in the database (default 0) so it must never
     * reach validated() as null — an explicit null in the update/insert
     * payload overrides the column default and throws.
     */
    protected function prepareForValidation(): void
    {
        $last = Topic::query()
            ->where('parent_id', $this->input('parent_id') ?: null)
            ->max('sort_order');

        $this->merge([
            // The select submits an empty string for "geen wachtwoord"; the
            // column is a nullable FK, so that has to become a real null.
            'access_password_id' => $this->input('access_password_id') === '' ? null : $this->input('access_password_id'),
            // Ordering is done by dragging, so the form no longer asks for a
            // number. A new topic goes to the end of its own parent's list.
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? ($last === null ? 0 : $last + 1)
                : $this->input('sort_order'),
        ]);
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable', 'integer', Rule::exists('topics', 'id'),
                function (string $attribute, mixed $value, Closure $fail) {
                    $parent = $value === null ? null : Topic::find($value);

                    if ($parent !== null && $parent->depth >= 2) {
                        $fail('Onderwerpen kunnen maximaal 3 niveaus diep zijn.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                new SiblingSlugIsUnique($this->input('parent_id') !== null ? (int) $this->input('parent_id') : null),
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
