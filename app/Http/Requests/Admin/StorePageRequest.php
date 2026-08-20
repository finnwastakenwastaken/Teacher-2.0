<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Rules\SiblingSlugIsUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * sort_order is NOT NULL in the database (default 0) so it must never
     * reach validated() as null — an explicit null in the insert payload
     * overrides the column default and throws.
     */
    protected function prepareForValidation(): void
    {
        $last = Page::query()
            ->where('topic_id', $this->input('topic_id'))
            ->max('sort_order');

        $this->merge([
            // The select submits an empty string for "geen wachtwoord"; the
            // column is a nullable FK, so that has to become a real null.
            'access_password_id' => $this->input('access_password_id') === '' ? null : $this->input('access_password_id'),
            'hero_image_id' => $this->input('hero_image_id') === '' ? null : $this->input('hero_image_id'),
            // Ordering is done by dragging, so the form no longer asks for a
            // number. A new page goes to the end of its topic's list.
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? ($last === null ? 0 : $last + 1)
                : $this->input('sort_order'),
        ]);
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer', Rule::exists('topics', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                new SiblingSlugIsUnique($this->input('topic_id') !== null ? (int) $this->input('topic_id') : null),
            ],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer'],
            'access_password_id' => ['nullable', 'integer', Rule::exists('access_passwords', 'id')],

            'hero_image_id' => ['nullable', 'integer', Rule::exists('images', 'id')],
            'is_hidden' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'topic_id.required' => 'Kies een onderwerp voor deze pagina.',
            'title.required' => 'Vul een titel in.',
            'slug.required' => 'Vul een slug in.',
            'slug.regex' => 'De slug mag alleen kleine letters, cijfers en koppeltekens bevatten.',
        ];
    }
}
