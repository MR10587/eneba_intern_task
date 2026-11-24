<?php

namespace App\Http\Requests\Api;

/**
 * Validation for GET /api/orders.
 *
 * WHY: the same bounded pagination shape as catalog/search keeps list
 * components reusable and prevents a client from pulling every order at once.
 */
class OrderListRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('catalog.max_per_page');

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
        ];
    }
}
