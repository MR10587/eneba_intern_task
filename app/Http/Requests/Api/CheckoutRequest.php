<?php

namespace App\Http\Requests\Api;

/**
 * Validation for POST /api/checkout.
 *
 * WHY: only a simulated card number is accepted. It is normalized here so
 * spaces and dashes are user-friendly, but only digits reach the service —
 * and only the masked last 4 is ever persisted.
 */
class CheckoutRequest extends ApiFormRequest
{
    /**
     * Strip formatting characters before validation.
     */
    protected function prepareForValidation(): void
    {
        // Guard the type first: an array must fail `string` below rather than
        // raising a warning here.
        if (is_string($this->input('card_number'))) {
            $this->merge([
                'card_number' => preg_replace('/[\s-]+/', '', $this->input('card_number')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'regex:/^\d{13,19}$/'],
        ];
    }
}
