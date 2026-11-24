<?php

namespace App\Http\Requests\Api;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for every API FormRequest.
 *
 * WHY: Laravel normally redirects back on validation failure with a 302 for
 * HTML clients. API clients must instead receive a 422 JSON body with the
 * stable VALIDATION_ERROR code, so we override failedValidation() here once.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * All API requests are authorised unless a specific request says otherwise.
     * Ownership checks for signed-in resources belong in policies/services.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert validator failures into the shared coded API exception.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw ApiException::validation($validator->errors()->toArray());
    }
}
