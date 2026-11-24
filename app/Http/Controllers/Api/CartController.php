<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer for the authenticated cart endpoints.
 *
 * WHY: middleware guarantees a user; the service owns all writes and pricing;
 * resources and ApiResponse own the response shape.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * GET /api/cart
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->cart->contents($request->user());

        return ApiResponse::success(new CartResource($result['cart']), [
            'item_count' => $result['item_count'],
            // WHY: lets the SPA warn about stale items without rechecking stock.
            'available_item_count' => $result['available_item_count'],
        ]);
    }

    /**
     * POST /api/cart/items
     */
    public function store(CartItemRequest $request): JsonResponse
    {
        $result = $this->cart->add(
            $request->user(),
            (int) $request->validated('product_id'),
            (int) $request->validated('offer_id'),
        );

        return ApiResponse::success(
            new CartResource($result['cart']),
            [
                'item_count' => $result['cart']->items->count(),
                'available_item_count' => $result['cart']->items
                    ->where('available', true)
                    ->count(),
            ],
            // WHY: duplicate adds are idempotent, so they are 200, not 201.
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * DELETE /api/cart/items/{offer}
     */
    public function destroy(Request $request, int $offer): JsonResponse
    {
        $cart = $this->cart->remove($request->user(), $offer);

        return ApiResponse::success(new CartResource($cart));
    }
}
