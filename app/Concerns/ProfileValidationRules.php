<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Lower-cases the address before anything looks at it, so the owner may
     * type `Teacher@school.nl` and have it work. The `lowercase` rule below
     * asserts the result rather than policing the input — refusing a capital
     * letter on the claim screen would be a strange thing to explain, and the
     * value has to end up lower-case regardless (see User::email()).
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower($email)]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * `lowercase` is not cosmetic and not only a duplicate of the mutator on
     * User: the uniqueness check below runs on what was submitted, so without
     * it a mixed-case address is compared against lower-case stored ones,
     * passes, and then collides once the mutator writes it. It also makes the
     * screen agree with what is stored rather than silently changing it.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'lowercase',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
