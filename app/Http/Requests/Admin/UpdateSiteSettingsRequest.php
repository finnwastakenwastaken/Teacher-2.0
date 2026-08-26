<?php

namespace App\Http\Requests\Admin;

use App\Support\ContentLanguage;
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
            'home_content.type' => ['required_with:home_content', 'string', 'in:doc'],
            'privacy_content' => ['nullable', 'array'],
            'privacy_content.type' => ['required_with:privacy_content', 'string', 'in:doc'],
            // An allow-list, not a free string: this value reaches a
            // PostgreSQL text-search configuration lookup. The lookup already
            // falls back rather than throwing, so this is the second of two
            // layers, not the only one.
            'content_language' => ['required', Rule::in(ContentLanguage::names())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_title.required' => __('admin.settings.title_required'),
            'home_heading.required' => __('admin.settings.heading_required'),
            'home_content.type.in' => __('admin.settings.content_unreadable'),
            'privacy_content.type.in' => __('admin.settings.content_unreadable'),
            'site_logo_image_id.exists' => __('admin.settings.image_missing'),
            'site_favicon_image_id.exists' => __('admin.settings.image_missing'),
            'home_banner_image_id.exists' => __('admin.settings.image_missing'),
            'content_language.in' => __('admin.settings.content_language_unknown'),
        ];
    }
}
