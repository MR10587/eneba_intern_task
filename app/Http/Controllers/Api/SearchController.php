<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\ProductResource;
use App\Services\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Thin HTTP layer for GET /api/search.
 *
 * WHY: validation, search logic and serialisation each have one owner —
 * SearchRequest, SearchService and ApiResponse respectively.
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $result = $this->search->search($request->validated());
        $paginator = $result['paginator'];

        return ApiResponse::success(
            ProductResource::collection($paginator->items()),
            [
                'query' => $result['query'],
                // WHY: the SPA needs the full match count, not just the number
                // of rows on the current page.
                'result_count' => $paginator->total(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        );
    }
}
