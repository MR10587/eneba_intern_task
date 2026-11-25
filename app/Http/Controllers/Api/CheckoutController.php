<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\ApiResponse;
use App\Support\CartIdentity;
use Illuminate\Http\JsonResponse;

/**
 * Thin HTTP layer for POST /api/checkout.
 *
 * WHY: the controller validates, delegates the transaction to CheckoutService,
 * and serialises the resulting order. It never touches prices or keys itself.
 * A guest cart carried into sign-in is merged here as a final safety net.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartService $cart,
    ) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $identity = CartIdentity::fromRequest($request);

        if ($identity->user !== null) {
            $this->cart->mergeGuest($identity->user, $identity->guestToken);
        }

        $result = $this->checkout->checkout(
            $request->user(),
            (string) $request->validated('card_number'),
        );

        return ApiResponse::success(
            new OrderResource($result['order']),
            // WHY: confirms how many keys were actually delivered without
            // making the client count order items itself.
            ['keys_delivered' => $result['keys_delivered']],
            201,
        );
    }
}
