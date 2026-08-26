<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of an autosaved concept, and nothing more.
 *
 * What may be *inside* a document is decided by App\Support\PageContent, not
 * here: the whitelist has to be applied recursively to every node, mark and
 * attribute, which is not something a rule table can express. This checks only
 * that a document arrived at all.
 *
 * Nothing reads validated(). It returns only the keys that carry rules, so it
 * would hand back a document stripped down to ['type' => 'doc'] and the
 * controller would autosave an empty page over the owner's work — the same
 * trap PageController::updateContent() documents. The controller reads
 * input('content') and Page::writeDraft() sanitises it.
 */
class StorePageDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gated by the 'auth' middleware; there is only ever one admin, so no
        // per-resource policy is needed on top of that.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `present`, not merely `nullable`, for the reason spelled out on
            // PageController::updateContent(): an absent key and an explicit
            // null are different things. Null is "the owner emptied this
            // page", absence is a malformed request, and with `nullable`
            // alone a client that failed to send the field would silently
            // replace the concept with nothing.
            'content' => ['present', 'nullable', 'array'],
            'content.type' => ['required_with:content', 'string', 'in:doc'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.type.in' => __('admin.pages.content_unreadable'),
        ];
    }
}
