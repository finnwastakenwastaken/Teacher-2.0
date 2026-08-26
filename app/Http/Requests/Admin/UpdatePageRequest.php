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
     * sort_order is NOT NULL, so an explicit null in the update payload would
     * throw. Order is set by dragging and the form never sends this field, so
     * the current value is kept rather than defaulted to 0 (which would
     * reshuffle the list on every unrelated edit); a page moved to a
     * different topic joins the end of that topic's list instead.
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

    /**
     * @return array<string, mixed>
     */
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
            'topic_id.required' => __('admin.pages.topic_required'),
            'title.required' => __('admin.fields.title_required'),
            'slug.required' => __('admin.fields.slug_required'),
            'slug.regex' => __('admin.fields.slug_format'),
        ];
    }
}
