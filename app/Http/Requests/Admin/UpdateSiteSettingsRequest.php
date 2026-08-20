<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_title' => ['required', 'string', 'max:120'],
            'site_logo_image_id' => ['nullable', Rule::exists('images', 'id')],
            'site_favicon_image_id' => ['nullable', Rule::exists('images', 'id')],
            'home_heading' => ['required', 'string', 'max:160'],
            'home_subheading' => ['nullable', 'string', 'max:400'],
            'home_banner_image_id' => ['nullable', Rule::exists('images', 'id')],
            // Only the shape is validated. The document itself is
            // whitelisted server-side by App\Support\PageContent, which is
            // what actually decides which nodes survive — see the gotcha in
            // The technical reference about validated() silently erasing the body.
            'home_content' => ['nullable', 'array'],
            'home_content.type' => ['required_with:home_content', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_title.required' => 'Geef de site een titel.',
            'home_heading.required' => 'Geef de homepage een kop.',
            'site_logo_image_id.exists' => 'Die afbeelding bestaat niet.',
            'site_favicon_image_id.exists' => 'Die afbeelding bestaat niet.',
            'home_banner_image_id.exists' => 'Die afbeelding bestaat niet.',
        ];
    }
}
