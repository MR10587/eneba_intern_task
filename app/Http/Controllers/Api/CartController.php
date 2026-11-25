<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use App\Support\ApiResponse;
use App\Support\CartIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer for the public cart endpoints.
 *
 * WHY: guests may browse and fill a cart, but only checkout needs a token.
 * The service owns cart identity, all writes, and pricing; resources and
 * ApiResponse own the response shape.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * GET /api/cart
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->cart->contents(CartIdentity::fromRequest($request));

        return ApiResponse::success(new CartResource($result['cart']), $this->meta($result));
    }

    /**
     * POST /api/cart/items
     */
    public function store(CartItemRequest $request): JsonResponse
    {
        $result = $this->cart->add(
            CartIdentity::fromRequest($request),
            (int) $request->validated('product_id'),
            (int) $request->validated('offer_id'),
        );

        return ApiResponse::success(
            new CartResource($result['cart']),
            $this->meta($result),
            // WHY: duplicate adds are idempotent, so they are 200, not 201.
            $result['created'] ? 201 : 200,
        );
    }

    /**
     * DELETE /api/cart/items/{offer}
     */
    public function destroy(Request $request, int $offer): JsonResponse
    {
        $result = $this->cart->remove(CartIdentity::fromRequest($request), $offer);

        return ApiResponse::success(new CartResource($result['cart']), $this->meta($result));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function meta(array $result): array
    {
        $meta = [
            'item_count' => $result['item_count'] ?? $result['cart']->items->count(),
            'available_item_count' => $result['available_item_count'] ?? $result['cart']->items
                ->where('available', true)
                ->count(),
        ];

        // WHY: a guest needs this once so later requests can name the same cart.
        if (($result['cart_token'] ?? null) !== null) {
            $meta['cart_token'] = $result['cart_token'];
        }

        return $meta;
    }
}
