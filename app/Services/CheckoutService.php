<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DigitalKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place money is computed and digital keys are allocated.
 *
 * Invariants owned by this class:
 *  1. A failed payment creates no order, sells no key, and leaves the cart intact.
 *  2. The order row exists before any order item (order_items.order_id is a FK).
 *  3. A key's status is re-checked after locking, and the conditional UPDATE
 *     plus the unique digital_key_id index make double assignment impossible.
 *  4. Totals come exclusively from persisted offers.price_cents.
 */
class CheckoutService
{
    /**
     * @return array{order: Order, keys_delivered: int}
     *
     * @throws ApiException for an empty/stale cart, mixed currencies, or a
     *                      simulated payment decline
     */
    public function checkout(User $user, string $cardNumber): array
    {
        // WHY: one transaction covers validation, order creation, key
        // allocation and cart clearing. Any thrown ApiException rolls every
        // write back, so partial orders and half-assigned keys cannot exist.
        return DB::transaction(function () use ($user, $cardNumber): array {
            $cart = Cart::query()->where('carts.user_id', $user->id)->first();

            if ($cart === null) {
                throw ApiException::validation(['cart' => ['Your cart is empty.']]);
            }

            $items = CartItem::query()
                ->where('cart_items.cart_id', $cart->id)
                ->with('offer')
                ->orderBy('cart_items.id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ApiException::validation(['cart' => ['Your cart is empty.']]);
            }

            $unitPrices = [];
            $totalCents = 0;
            $currency = config('catalog.currency');

            // WHY: revalidate live state. The cart's cached `available` flag
            // can be stale by the time the user actually pays.
            foreach ($items as $item) {
                $offer = $item->offer;

                if ($offer === null) {
                    throw ApiException::offerNotFound();
                }

                $product = Product::query()->where('products.id', $offer->product_id)->first();

                if ($product === null || ! (bool) $product->is_active) {
                    throw ApiException::productNotFound();
                }

                if (! (bool) $offer->is_active) {
                    throw ApiException::insufficientInventory();
                }

                if ($offer->currency !== $currency) {
                    throw ApiException::validation(['cart' => ['Mixed currencies are not supported.']]);
                }

                $unitPrices[$item->id] = (int) $offer->price_cents;
                $totalCents += $unitPrices[$item->id];
            }

            $last4 = substr($cardNumber, -4);

            // WHY: throw BEFORE creating anything. The rejected payment leaves
            // no order row — not even one marked failed — and no sold keys.
            if ($last4 === '0002') {
                throw ApiException::paymentFailed();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'completed',
                'total_cents' => $totalCents,
                'currency' => $currency,
                'payment_status' => 'succeeded',
                // WHY: only the masked last 4 is persisted. The full card
                // number is never stored or logged.
                'payment_reference' => 'SIM-'.Str::upper(Str::random(8)).'-'.$last4,
            ]);

            foreach ($items as $item) {
                $offer = $item->offer;

                $key = DigitalKey::query()
                    ->where('digital_keys.offer_id', $offer->id)
                    ->where('digital_keys.status', 'available')
                    ->orderBy('digital_keys.id')
                    ->lockForUpdate()
                    ->first();

                // WHY: re-apply the status filter after the lock. A concurrent
                // checkout may have sold this key while we were waiting.
                if ($key === null || $key->status !== 'available') {
                    throw ApiException::insufficientInventory();
                }

                // Second guard: closes the gap between SELECT and UPDATE.
                $updated = DigitalKey::query()
                    ->where('digital_keys.id', $key->id)
                    ->where('digital_keys.status', 'available')
                    ->update(['digital_keys.status' => 'sold']);

                if ($updated !== 1) {
                    throw ApiException::insufficientInventory();
                }

                try {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'offer_id' => $offer->id,
                        'quantity' => 1,
                        'unit_price_cents' => $unitPrices[$item->id],
                        'digital_key_id' => $key->id,
                    ]);
                } catch (QueryException) {
                    // WHY: the unique digital_key_id index is the final hard
                    // constraint. Translate a rare race into a coded error.
                    throw ApiException::insufficientInventory();
                }
            }

            // Keep the cart so the user can shop again; only the lines that
            // were paid for are removed.
            CartItem::query()->where('cart_items.cart_id', $cart->id)->delete();

            $order->load(['items.offer.product', 'items.digitalKey']);

            return ['order' => $order, 'keys_delivered' => $items->count()];
        });
    }
}
