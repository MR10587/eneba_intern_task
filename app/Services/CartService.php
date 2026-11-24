<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;

/**
 * All cart writes and pricing live here.
 *
 * Invariants owned by this class:
 *  1. A cart always belongs to exactly one authenticated user.
 *  2. Only an active product + active offer + available key may be added.
 *  3. Prices and totals are read from persisted offers, never from the client.
 *  4. A stale cart item is reported as unavailable, never silently repriced.
 */
class CartService
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Build the current user's cart with backend-computed line prices.
     *
     * @return array{cart: Cart, item_count: int, available_item_count: int}
     */
    public function contents(User $user): array
    {
        $cart = $this->cartFor($user);

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
     * Add an offer to the user's cart.
     *
     * @return array{cart: Cart, created: bool}
     *
     * @throws ApiException for missing/inactive product, missing/wrong-product
     *                      offer, or insufficient inventory
     */
    public function add(User $user, int $productId, int $offerId): array
    {
        $cart = $this->cartFor($user);

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

        return ['cart' => $this->contents($user)['cart'], 'created' => $created];
    }

    /**
     * Remove one offer from the user's own cart.
     *
     * @throws ApiException when that offer is not in this cart
     */
    public function remove(User $user, int $offerId): Cart
    {
        $cart = $this->cartFor($user);

        $item = CartItem::query()
            ->where('cart_items.cart_id', $cart->id)
            ->where('cart_items.offer_id', $offerId)
            ->first();

        if ($item === null) {
            throw ApiException::notFound();
        }

        $item->delete();

        return $this->contents($user)['cart'];
    }

    /**
     * There is one cart per user; the client never supplies a cart ID.
     */
    private function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }
}
