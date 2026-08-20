<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A download without an explicit order joins the end of the page's list.
     *
     * Uploading several worksheets at once attaches them one after another,
     * and each of those requests is sent before the previous response has
     * updated the editor's copy of the list — so the client cannot count.
     * The server can, and it is the only one that sees them in sequence.
     */
    protected function prepareForValidation(): void
    {
        $last = $this->route('page')->downloads()->max('sort_order');

        $this->merge([
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? ($last === null ? 0 : $last + 1)
                : $this->input('sort_order'),
        ]);
    }

    public function rules(): array
    {
        return [
            'media_file_id' => [
                'required', 'integer', Rule::exists('media_files', 'id'),
                // One page offers a given file once; the fix for a duplicate
                // is to edit the existing card, not to add a second one.
                Rule::unique('page_downloads', 'media_file_id')
                    ->where('page_id', $this->route('page')->id),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            // Levels are optional. A general handout that applies to every
            // track should not force the owner to tick every box; untagged
            // downloads render in their own group ahead of the rest.
            'education_levels' => ['array'],
            'education_levels.*' => ['integer', Rule::exists('education_levels', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'media_file_id.required' => 'Kies een bestand.',
            'media_file_id.exists' => 'Het gekozen bestand bestaat niet.',
            'media_file_id.unique' => 'Dit bestand staat al bij de downloads van deze pagina.',
        ];
    }
}
