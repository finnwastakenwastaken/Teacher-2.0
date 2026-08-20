<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Rules\SiblingSlugIsUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
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
     * Defaulting it to 0 would therefore throw the topic's page order away
     * every time the owner saved an unrelated edit; the current value is kept
     * instead. A page moved to a different topic joins the end of that
     * topic's list, because its old number means nothing there.
     */
    protected function prepareForValidation(): void
    {
        /** @var Page $page */
        $page = $this->route('page');

        $topicId = $this->input('topic_id');

        $this->merge([
            // The select submits an empty string for "geen wachtwoord"; the
            // column is a nullable FK, so that has to become a real null.
            'access_password_id' => $this->input('access_password_id') === '' ? null : $this->input('access_password_id'),
            'hero_image_id' => $this->input('hero_image_id') === '' ? null : $this->input('hero_image_id'),
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? $this->keptOrEndOfList($page, $topicId === null ? null : (int) $topicId)
                : $this->input('sort_order'),
        ]);
    }

    private function keptOrEndOfList(Page $page, ?int $topicId): int
    {
        if ($topicId === null || $topicId === (int) $page->topic_id) {
            return $page->sort_order;
        }

        $last = Page::query()->where('topic_id', $topicId)->max('sort_order');

        return $last === null ? 0 : $last + 1;
    }

    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');

        return [
            'topic_id' => ['required', 'integer', Rule::exists('topics', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                new SiblingSlugIsUnique(
                    $this->input('topic_id') !== null ? (int) $this->input('topic_id') : null,
                    Page::class,
                    $page->id,
                ),
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
