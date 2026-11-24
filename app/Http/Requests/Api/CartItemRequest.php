<?php

namespace App\Http\Requests\Api;

/**
 * Validation for POST /api/cart/items.
 *
 * WHY: only the two identifiers are accepted. Prices, quantities and totals
 * are deliberately absent because the service derives them from the DB.
 * Existence is checked in CartService so failures use coded business errors
 * (PRODUCT_NOT_FOUND / OFFER_NOT_FOUND) instead of a generic validation error.
 */
class CartItemRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'offer_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
