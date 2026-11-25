<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation for GET /api/search.
 *
 * WHY: a single short query string is the MVP search contract — autocomplete
 * and "did you mean" suggestions are explicit non-goals. The marketplace also
 * sends the same category/platform/region/price filters as /products so
 * search and catalog pagination cannot disagree.
 */
class SearchRequest extends ApiFormRequest
{
    /**
     * Trim the term so "  minecraft  " behaves like "minecraft".
     */
    protected function prepareForValidation(): void
    {
        // Guard the type before normalising: an array `q` must fail the
        // `string` rule below, not be coerced into a PHP warning here.
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('catalog.max_per_page');

        return [
            'q' => ['required', 'string', 'min:2', 'max:64'],
            'category' => ['sometimes', Rule::in(config('catalog.categories'))],
            'platform' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
        ];
    }

    /**
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
