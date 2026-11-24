<?php

namespace App\Http\Requests\Api;

/**
 * Validation for POST /api/auth/register.
 *
 * WHY: email + password is the only supported sign-in method (no social
 * login, no email verification), so these four fields are the whole contract.
 */
class RegisterRequest extends ApiFormRequest
{
    /**
     * Normalise the email before the unique rule runs.
     *
     * WHY: PostgreSQL string comparison is case-sensitive here. Without this,
     * "Ada@example.com" and "ada@example.com" could become two accounts.
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // `confirmed` requires a matching password_confirmation field.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
