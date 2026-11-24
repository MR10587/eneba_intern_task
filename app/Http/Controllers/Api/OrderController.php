<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderListRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer for the signed-in order history endpoints.
 *
 * WHY: OrderService owns the ownership scoping, so the controller only
 * validates pagination, serialises, and adds the shared pagination envelope.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(OrderListRequest $request): JsonResponse
    {
        $paginator = $this->orders->paginateFor($request->user(), $request->validated());

        return ApiResponse::success(
            OrderResource::collection($paginator->items()),
            [
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

    public function show(Request $request, int $order): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->orders->findForUser($request->user(), $order)),
        );
    }
}
