<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomeBlockResource;
use App\Services\Home\HomeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Thin HTTP layer for GET /api/home.
 *
 * WHY: the controller only delegates to HomeService and serialises with the
 * shared envelope. All block selection and content rules stay in the service.
 */
class HomeController extends Controller
{
    public function __construct(private readonly HomeService $home) {}

    public function __invoke(): JsonResponse
    {
        $blocks = $this->home->blocks();

        return ApiResponse::success(
            HomeBlockResource::collection($blocks),
            // WHY: lets the SPA distinguish "no configuration yet" from a
            // failed/empty response without hardcoding expected block counts.
            ['block_count' => $blocks->count()],
        );
    }
}
