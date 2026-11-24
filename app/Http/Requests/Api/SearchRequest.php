<?php

namespace App\Http\Requests\Api;

/**
 * Validation for GET /api/search.
 *
 * WHY: a single short query string is the whole MVP search contract —
 * autocomplete and "did you mean" suggestions are explicit non-goals.
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
            // Search supports the same pagination shape as the catalog so the
            // SPA can reuse its list components.
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', "max:{$maxPerPage}"],
        ];
    }
}
