<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => filled($this->input('slug'))
                ? Str::slug((string) $this->input('slug'))
                : Str::slug((string) $this->input('name')),
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? 0
                : $this->input('sort_order'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'required', 'string', 'max:60',
                Rule::unique('education_levels', 'slug')->ignore($this->route('level')),
            ],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('admin.levels.name_required'),
            'slug.unique' => __('admin.levels.name_taken'),
        ];
    }
}
