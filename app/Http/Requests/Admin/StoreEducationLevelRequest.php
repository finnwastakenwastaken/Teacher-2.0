<?php

namespace App\Http\Requests\Admin;

use App\Models\EducationLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEducationLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the 'auth' middleware; there is only ever one admin.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $last = EducationLevel::query()->max('sort_order');

        $this->merge([
            // The slug is a URL-safe handle for a name the owner types in
            // Dutch, and it is never shown to them. Derive it rather than
            // asking for it; they can still send one explicitly.
            'slug' => filled($this->input('slug'))
                ? Str::slug((string) $this->input('slug'))
                : Str::slug((string) $this->input('name')),
            // Ordering is done by dragging, so the form no longer asks for a
            // number. A new level goes to the end, which is where someone
            // adding one expects to find it.
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === ''
                ? ($last === null ? 0 : $last + 1)
                : $this->input('sort_order'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', Rule::unique('education_levels', 'slug')],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vul een naam in.',
            'slug.required' => 'De naam moet minstens één letter of cijfer bevatten.',
            'slug.unique' => 'Er bestaat al een niveau met deze naam.',
        ];
    }
}
