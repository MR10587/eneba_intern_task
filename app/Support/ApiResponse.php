<?php

namespace App\Support;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The single place that builds JSON API responses.
 *
 * Contract:
 *   success -> { "data": ..., "meta": { ... } }   (meta omitted when empty)
 *   error   -> { "message": "...", "code": "...", "errors": { ... } }
 *
 * WHY: centralising the envelope means no controller can accidentally return
 * a bare resource and break the SPA's typed client.
 */
final class ApiResponse
{
    /**
     * Build a successful response.
     *
     * JsonResource objects are passed straight through: because
     * JsonResource::withoutWrapping() is set in AppServiceProvider, they
     * serialise as plain arrays inside our own "data" key.
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build an error response. `errors` is omitted outside validation so the
     * SPA can rely on its presence as the validation signal.
     */
    public static function error(
        ErrorCode $code,
        string $message,
        array $errors = [],
        ?int $status = null,
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'code' => $code->value,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status ?? $code->status());
    }
}
