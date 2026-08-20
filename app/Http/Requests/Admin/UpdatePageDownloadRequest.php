<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which file a download points at is fixed once created — swapping it would
 * silently change what students get from a link they may already have. To
 * offer a different file, remove this download and add the other one.
 */
class UpdatePageDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? 0
                : $this->input('sort_order'),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            'education_levels' => ['array'],
            'education_levels.*' => ['integer', Rule::exists('education_levels', 'id')],
        ];
    }
}
