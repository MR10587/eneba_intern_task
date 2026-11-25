<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Support\CartIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All cart writes and pricing live here.
 *
 * Invariants owned by this class:
 *  1. Signed-in users see only their own cart; guests see only the cart named
 *     by their server-issued token. A guest cart merges into the user cart at
 *     sign-in and is then deleted.
 *  2. Only an active product + active offer + available key may be added.
 *  3. Prices and totals are read from persisted offers, never from the client.
 *  4. A stale cart item is reported as unavailable, never silently repriced.
 */
class CartService
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * @return array{cart: Cart, item_count: int, available_item_count: int, cart_token: string|null}
     */
    public function contents(CartIdentity $identity): array
    {
        [$cart, $cartToken] = $this->resolveCart($identity);

        return [
            ...$this->describe($cart),
            'cart_token' => $cartToken,
        ];
    }

    /**
     * @return array{cart: Cart, created: bool, cart_token: string|null}
     *
     * @throws ApiException for missing/inactive product, missing/wrong-product
     *                      offer, or insufficient inventory
     */
    public function add(CartIdentity $identity, int $productId, int $offerId): array
    {
        [$cart, $cartToken] = $this->resolveCart($identity);

        $product = Product::query()->where('products.id', $productId)->first();

        if ($product === null || ! (bool) $product->is_active) {
            throw ApiException::productNotFound();
        }

        $offer = Offer::query()->where('offers.id', $offerId)->first();

        // WHY: a mismatched pair is an offer error, not a product error. It
        // must not let a caller attach an offer from a different product.
        if ($offer === null || (int) $offer->product_id !== $productId) {
            throw ApiException::offerNotFound();
        }

        $hasAvailableKey = DigitalKey::query()
            ->where('digital_keys.offer_id', $offer->id)
            ->where('digital_keys.status', 'available')
            ->exists();

        if (! (bool) $offer->is_active || ! $hasAvailableKey) {
            throw ApiException::insufficientInventory();
        }

        $item = CartItem::firstOrCreate(
            ['cart_id' => $cart->id, 'offer_id' => $offer->id],
            ['quantity' => 1],
        );
        $created = $item->wasRecentlyCreated;

        return [
            'cart' => $this->describe($cart)['cart'],
            'created' => $created,
            'cart_token' => $cartToken,
        ];
    }

    /**
     * @return array{cart: Cart, cart_token: string|null}
     *
     * @throws ApiException when that offer is not in this cart
     */
    public function remove(CartIdentity $identity, int $offerId): array
    {
        [$cart, $cartToken] = $this->resolveCart($identity);

        $item = CartItem::query()
            ->where('cart_items.cart_id', $cart->id)
            ->where('cart_items.offer_id', $offerId)
            ->first();

        if ($item === null) {
            throw ApiException::notFound();
        }

        $item->delete();

        return [
            'cart' => $this->describe($cart)['cart'],
            'cart_token' => $cartToken,
        ];
    }

    /**
     * Move a guest's lines into the user's cart once credentials are verified.
     * Duplicate offers are collapsed; the guest cart is then removed so the
     * token cannot be replayed to reach those lines again.
     */
    public function mergeGuest(User $user, ?string $guestToken): void
    {
        $token = CartIdentity::validToken($guestToken);

        if ($token === null) {
            return;
        }

        DB::transaction(function () use ($user, $token): void {
            $guestCart = Cart::query()->where('carts.guest_token', $token)->first();

            if ($guestCart === null) {
                return;
            }

            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

            foreach ($guestCart->items()->orderBy('cart_items.id')->get() as $item) {
                CartItem::firstOrCreate(
                    ['cart_id' => $userCart->id, 'offer_id' => $item->offer_id],
                    ['quantity' => $item->quantity],
                );
            }

            $guestCart->items()->delete();
            $guestCart->delete();
        });
    }

    /**
     * Load the cart and compute every line price from the persisted offer.
     *
     * @return array{cart: Cart, item_count: int, available_item_count: int}
     */
    private function describe(Cart $cart): array
    {
        $cart->load(['items.offer' => function ($query): void {
            // WHY: stale offers stay in the cart, so availability must be
            // computed from persisted status/key count rather than filtering
            // them out of the query.
            $query->with('seller')
                ->withCount(['digitalKeys as available_key_count' => function ($keys): void {
                    $keys->where('digital_keys.status', 'available');
                }]);
        }]);

        $items = $cart->items;
        $productIds = $items->pluck('offer.product_id')->filter()->unique()->all();
        $products = collect();

        if ($productIds !== []) {
            $productQuery = Product::query()->whereIn('products.id', $productIds);
            // WHY: reuse the catalog pricing rule so a product summary in the
            // cart has exactly the same min_price_cents semantics as /products.
            // Do not filter is_active: inactive products can already be in a
            // cart and must remain visible with available: false.
            $this->catalog->addComputedPriceColumns($productQuery);
            $products = $productQuery->get()->keyBy('id');
        }

        $availableCount = 0;

        foreach ($items as $item) {
            $offer = $item->offer;
            $product = $products->get($offer->product_id);
            $offer->setRelation('product', $product);

            // Quantity is fixed at 1, so the persisted offer price IS the line
            // total. No arithmetic based on client input is ever performed.
            $available = $product !== null
                && (bool) $product->is_active
                && (bool) $offer->is_active
                && (int) ($offer->available_key_count ?? 0) > 0;

            $item->setAttribute('line_total_cents', (int) $offer->price_cents);
            $item->setAttribute('available', $available);

            if ($available) {
                $availableCount++;
            }
        }

        $cart->setAttribute('total_cents', $items->sum('line_total_cents'));

        return [
            'cart' => $cart,
            'item_count' => $items->count(),
            'available_item_count' => $availableCount,
        ];
    }

    /**
     * Resolve exactly one cart for the request identity. A missing guest token
     * is issued here so the first anonymous add can be persisted and echoed
     * back to the SPA.
     *
     * @return array{Cart, string|null}
     */
    private function resolveCart(CartIdentity $identity): array
    {
        if ($identity->user !== null) {
            $this->mergeGuest($identity->user, $identity->guestToken);

            return [Cart::firstOrCreate(['user_id' => $identity->user->id]), null];
        }

        if ($identity->guestToken !== null) {
            return [
                Cart::firstOrCreate(['guest_token' => $identity->guestToken]),
                $identity->guestToken,
            ];
        }

        $guestToken = Str::uuid()->toString();

        return [Cart::create(['guest_token' => $guestToken]), $guestToken];
    }
}
