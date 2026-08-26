<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
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
        $last = $this->page()->downloads()->max('sort_order');

        $this->merge([
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? ($last === null ? 0 : $last + 1)
                : $this->input('sort_order'),
        ]);
    }

    /**
     * Exactly one of the two libraries, mirroring the CHECK constraint.
     *
     * `required_without` and `prohibits` together are the pair: neither given
     * is an attachment offering nothing, both given is one whose two halves
     * can disagree about what a student gets. The database refuses both cases
     * regardless — this is what turns the refusal into a form error naming
     * the field instead of a 500.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_file_id' => [
                'nullable', 'required_without:image_id', 'prohibits:image_id',
                'integer', Rule::exists('media_files', 'id'),
                // One page offers a given file once; the fix for a duplicate
                // is to edit the existing card, not to add a second one.
                Rule::unique('page_downloads', 'media_file_id')
                    ->where('page_id', $this->page()->id),
            ],
            // A poster, a scanned worksheet, a diagram meant to be handed out.
            // The library a file lands in is decided by sniffing its bytes,
            // so this is the only way any of those can be offered at all.
            'image_id' => [
                'nullable', 'required_without:media_file_id', 'prohibits:media_file_id',
                'integer', Rule::exists('images', 'id'),
                Rule::unique('page_downloads', 'image_id')
                    ->where('page_id', $this->page()->id),
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

    /**
     * route() is typed object|string|null, so calling a relation straight on
     * it turns a route-model-binding miss into a fatal instead of a 404. The
     * binding cannot miss on a route that is bound — but a request class is
     * exactly where that assumption stops being visible.
     */
    private function page(): Page
    {
        $page = $this->route('page');

        abort_unless($page instanceof Page, 404);

        return $page;
    }

    public function messages(): array
    {
        return [
            'media_file_id.required_without' => __('admin.downloads.file_required'),
            'media_file_id.prohibits' => __('admin.downloads.one_source_only'),
            'media_file_id.exists' => __('admin.downloads.file_missing'),
            'media_file_id.unique' => __('admin.downloads.already_attached'),
            'image_id.required_without' => __('admin.downloads.file_required'),
            'image_id.prohibits' => __('admin.downloads.one_source_only'),
            'image_id.exists' => __('admin.downloads.file_missing'),
            'image_id.unique' => __('admin.downloads.already_attached'),
        ];
    }
}
