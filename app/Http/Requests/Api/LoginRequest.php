<?php

namespace App\Http\Requests\Api;

/**
 * Validation for POST /api/auth/login.
 *
 * WHY: we validate shape only here. Whether the credentials are correct is a
 * business rule and is checked by AuthService with a constant-time hash check.
 */
class LoginRequest extends ApiFormRequest
{
    /**
     * Match RegisterRequest so addresses are stored and looked up identically.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge([
                'email' => mb_strtolower(trim($this->string('email')->toString())),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            // No min:8 here: password rules belong to registration, otherwise
            // existing credentials could become impossible to submit.
            'password' => ['required', 'string'],
        ];
    }
}
