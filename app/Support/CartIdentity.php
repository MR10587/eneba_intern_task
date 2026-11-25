<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Identifies whose cart a request may touch.
 *
 * Signed-in requests always use the user's own cart. Anonymous requests use
 * the `X-Cart-Token` header as a bearer-style guest token; the token is issued
 * by the server on first use and echoed in `meta.cart_token` for the SPA to
 * persist. A guest token can never reach a signed-in user's cart.
 */
final readonly class CartIdentity
{
    public function __construct(
        public ?User $user,
        public ?string $guestToken,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // WHY: cart routes are public, so no auth:sanctum middleware sets the
        // default guard. Ask Sanctum directly, or a Bearer token would be
        // ignored and a signed-in shopper would get a fresh guest cart.
        $user = Auth::guard('sanctum')->user();

        return new self(
            $user instanceof User ? $user : null,
            self::validToken($request->header('X-Cart-Token')),
        );
    }

    /**
     * Reject malformed tokens before they can become cart lookup keys.
     */
    public static function validToken(mixed $token): ?string
    {
        if (! is_string($token) || preg_match('/^[A-Za-z0-9_-]{32,128}$/', $token) !== 1) {
            return null;
        }

        return $token;
    }
}
