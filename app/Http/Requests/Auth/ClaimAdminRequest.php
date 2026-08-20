<?php

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Support\AdminSetupToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClaimAdminRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'setup_token' => AdminSetupToken::isConfigured()
                ? ['required', 'string']
                : ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * The setup token isn't checked as a normal field rule because a wrong
     * value isn't a per-field format problem — it's "you may not do this" —
     * and the constant-time comparison belongs in one place
     * (AdminSetupToken::matches()), not duplicated as a validation rule.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! AdminSetupToken::matches($this->input('setup_token'))) {
                $validator->errors()->add(
                    'setup_token',
                    __('Deze installatiecode is onjuist.'),
                );
            }
        });
    }
}
