<?php

namespace App\Http\Requests\Admin;

use App\Support\ThemePalette;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemePaletteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // One rule per known entry rather than a `palette.*` wildcard. The
        // wildcard would accept a key nobody declared and leave dropping it to
        // whatever reads the value next; naming them means an unknown key is
        // simply not in validated() and never reaches the database at all.
        $rules = [
            'palette' => ['nullable', 'array'],
        ];

        foreach (ThemePalette::keys() as $key) {
            $rules['palette.'.$key] = [
                'nullable',
                'string',
                // The security half of this feature, and it is not the
                // contrast gate. This value is written into a <style> block,
                // so it is matched against an anchored hex pattern and
                // refused if it does not fit — never trimmed, escaped or
                // otherwise talked into being a colour.
                'regex:'.ThemePalette::PATTERN,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'palette.*.regex' => __('admin.theme.not_a_colour'),
            'palette.*.string' => __('admin.theme.not_a_colour'),
        ];
    }
}
