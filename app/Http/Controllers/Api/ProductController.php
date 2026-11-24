<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Thin HTTP layer for the catalog slice.
 *
 * WHY: controllers only validate (via FormRequest), delegate to CatalogService,
 * and serialise with ApiResponse. All business rules stay in the service.
 */
class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * GET /api/products/filters
     */
    public function filters(): JsonResponse
    {
        return ApiResponse::success($this->catalog->filters());
    }

    /**
     * GET /api/products
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $result = $this->catalog->products($request->validated());
        $paginator = $result['paginator'];

        return ApiResponse::success(
            ProductResource::collection($paginator->items()),
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                // Echo back exactly what the client asked for, normalized.
                'applied_filters' => $result['applied_filters'],
            ],
        );
    }

    /**
     * GET /api/products/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->catalog->findBySlug($slug);

        return ApiResponse::success(new ProductResource($product), [
            'available_offer_count' => (int) $product->available_offer_count,
        ]);
    }
}
