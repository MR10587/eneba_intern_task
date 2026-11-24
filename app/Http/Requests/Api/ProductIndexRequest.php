<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validates GET /api/products query parameters only.
 *
 * WHY: a dedicated FormRequest keeps validation rules out of the controller
 * and guarantees that all failures use the shared VALIDATION_ERROR envelope.
 */
class ProductIndexRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('catalog.max_per_page');

        return [
            'platform' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],

            // Prices are always integer cents. Never accept decimals here.
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0'],

            'sort' => ['sometimes', 'string', 'in:price_asc,price_desc,popularity,relevance'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
        ];
    }

    /**
     * Cross-field checks that cannot be expressed as a simple rule.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('min_price') && $this->filled('max_price')
                    && (int) $this->input('min_price') > (int) $this->input('max_price')) {
                    $validator->errors()->add(
                        'max_price',
                        'max_price must be greater than or equal to min_price.',
                    );
                }
            },
        ];
    }
}
