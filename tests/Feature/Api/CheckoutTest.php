<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for POST /api/checkout.
 *
 * WHY: these pin the highest-risk invariants — no order on payment failure,
 * the order-before-items FK order, one unique sold key per line, and totals
 * derived only from persisted offer prices.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD_CARD = '4242424242424242';

    private const DECLINED_CARD = '4000000000000002';

    /**
     * @param  array<string, mixed>  $productAttributes
     * @return array{product: Product, offer: Offer, keys: Collection}
     */
    private function purchasableProduct(array $productAttributes = [], int $priceCents = 1999, int $keyCount = 1): array
    {
        $product = Product::factory()->create($productAttributes);
        $offer = Offer::factory()->for($product)->create(['price_cents' => $priceCents]);
        $keys = DigitalKey::factory()->count($keyCount)->for($offer)->create();

        return ['product' => $product, 'offer' => $offer, 'keys' => $keys];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    private function addToCart(string $token, Product $product, Offer $offer): void
    {
        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'offer_id' => $offer->id,
        ])->assertCreated();
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_card_number_is_validated(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        foreach ([null, 'abcd', '123456789012', '12345678901234567890'] as $invalid) {
            $payload = $invalid === null ? [] : ['card_number' => $invalid];

            $this->withToken($token)->postJson('/api/checkout', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['errors' => ['card_number']]);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_card_spaces_and_dashes_are_normalised(): void
    {
        $fixture = $this->purchasableProduct();
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);

        $response = $this->withToken($token)->postJson('/api/checkout', [
            'card_number' => '4242 4242-4242 4242',
        ])->assertCreated();

        $this->assertStringEndsWith('-4242', (string) $response->json('data.payment_reference'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_empty_cart_is_rejected_without_creating_an_order(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['cart']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_successful_checkout_creates_order_assigns_key_and_clears_cart(): void
    {
        $fixture = $this->purchasableProduct(['name' => 'Checkout Game'], 1999);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);
        $key = $fixture['keys']->first();

        $response = $this->withToken($token)->postJson('/api/checkout', [
            'card_number' => self::GOOD_CARD,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_status', 'succeeded')
            ->assertJsonPath('data.total_cents', 1999)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.items.0.unit_price_cents', 1999)
            ->assertJsonPath('data.items.0.line_total_cents', 1999)
            ->assertJsonPath('data.items.0.product.slug', $fixture['product']->slug)
            ->assertJsonPath('data.items.0.digital_key.code', $key->code)
            ->assertJsonPath('meta.keys_delivered', 1);

        $order = Order::firstOrFail();
        $this->assertSame($order->id, $response->json('data.id'));
        $this->assertSame($order->id, $response->json('data.order_id'));
        $this->assertStringStartsWith('SIM-', (string) $response->json('data.payment_reference'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame('sold', $key->fresh()->status);
        $this->assertDatabaseCount('cart_items', 0);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'offer_id' => $fixture['offer']->id,
            'quantity' => 1,
            'unit_price_cents' => 1999,
            'digital_key_id' => $key->id,
        ]);
    }

    public function test_multiple_items_get_distinct_keys_and_a_summed_total(): void
    {
        $first = $this->purchasableProduct(['name' => 'First Game'], 1000);
        $second = $this->purchasableProduct(['name' => 'Second Game'], 2500);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $first['product'], $first['offer']);
        $this->addToCart($token, $second['product'], $second['offer']);

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 3500)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('meta.keys_delivered', 2);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame('sold', $first['keys']->first()->fresh()->status);
        $this->assertSame('sold', $second['keys']->first()->fresh()->status);
    }

    public function test_key_with_lowest_id_is_allocated_first(): void
    {
        $fixture = $this->purchasableProduct([], 1999, 2);
        $firstKey = $fixture['keys']->sortBy('id')->first();
        $secondKey = $fixture['keys']->sortByDesc('id')->first();
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertCreated()
            ->assertJsonPath('data.items.0.digital_key.code', $firstKey->code);

        $this->assertSame('sold', $firstKey->fresh()->status);
        $this->assertSame('available', $secondKey->fresh()->status);
    }

    public function test_declined_payment_creates_no_order_and_leaves_cart_untouched(): void
    {
        $fixture = $this->purchasableProduct([], 1999, 2);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::DECLINED_CARD])
            ->assertStatus(402)
            ->assertJsonPath('code', 'PAYMENT_FAILED')
            ->assertJsonPath('message', 'Payment failed.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('cart_items', 1);

        foreach ($fixture['keys'] as $key) {
            $this->assertSame('available', $key->fresh()->status);
        }
    }

    public function test_stale_cart_state_is_rejected_and_rolled_back(): void
    {
        // Sold-out key after the item was added.
        $sold = $this->purchasableProduct([], 1000);
        $soldToken = $this->tokenFor(User::factory()->create());
        $this->addToCart($soldToken, $sold['product'], $sold['offer']);
        $sold['keys']->first()->update(['status' => 'sold']);

        $this->withToken($soldToken)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_INVENTORY');

        // WHY: feature tests reuse one app instance, so RequestGuard caches the
        // first resolved user. Clear it when switching to another user's token.
        Auth::forgetGuards();

        // Offer deactivated after the item was added.
        $deactivated = $this->purchasableProduct([], 2000);
        $deactivatedToken = $this->tokenFor(User::factory()->create());
        $this->addToCart($deactivatedToken, $deactivated['product'], $deactivated['offer']);
        $deactivated['offer']->update(['is_active' => false]);

        $this->withToken($deactivatedToken)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSUFFICIENT_INVENTORY');

        Auth::forgetGuards();

        // Product deactivated after the item was added. Insert directly because
        // cart-add itself would correctly reject an inactive product.
        $inactive = $this->purchasableProduct(['is_active' => false], 3000);
        $inactiveUser = User::factory()->create();
        $inactiveToken = $this->tokenFor($inactiveUser);
        $inactiveCart = Cart::firstOrCreate(['user_id' => $inactiveUser->id]);
        CartItem::factory()->create([
            'cart_id' => $inactiveCart->id,
            'offer_id' => $inactive['offer']->id,
        ]);

        $this->withToken($inactiveToken)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertNotFound()
            ->assertJsonPath('code', 'PRODUCT_NOT_FOUND');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_mixed_currency_cart_is_rejected(): void
    {
        $fixture = $this->purchasableProduct([], 1999);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);

        // WHY: a stale offer from before the EUR migration could still carry
        // USD. Checkout must refuse it instead of mixing currencies in a total.
        $fixture['offer']->update(['currency' => 'USD']);

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['cart']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_uses_current_offer_price_not_the_price_at_add_time(): void
    {
        $fixture = $this->purchasableProduct([], 1000);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->addToCart($token, $fixture['product'], $fixture['offer']);

        $fixture['offer']->update(['price_cents' => 1750]);

        $this->withToken($token)->postJson('/api/checkout', ['card_number' => self::GOOD_CARD])
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 1750)
            ->assertJsonPath('data.items.0.unit_price_cents', 1750);
    }

    public function test_key_uniqueness_guard_and_card_privacy(): void
    {
        $fixture = $this->purchasableProduct();
        $order = Order::factory()->create();
        $key = $fixture['keys']->first();
        $attributes = [
            'order_id' => $order->id,
            'offer_id' => $fixture['offer']->id,
            'quantity' => 1,
            'unit_price_cents' => 1999,
            'digital_key_id' => $key->id,
        ];

        OrderItem::create($attributes);

        try {
            // WHY: a nested transaction becomes a savepoint on PostgreSQL.
            // Without it, the unique violation aborts the RefreshDatabase
            // transaction and every later assertion fails with 25P02.
            DB::transaction(fn () => OrderItem::create($attributes));
            $this->fail('A key must not be assignable to two order items.');
        } catch (QueryException) {
            $this->assertSame(1, OrderItem::query()->where('digital_key_id', $key->id)->count());
        }

        // The full card number must never appear in a persisted order.
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $paid = $this->purchasableProduct([], 1999);
        $this->addToCart($token, $paid['product'], $paid['offer']);

        $response = $this->withToken($token)->postJson('/api/checkout', [
            'card_number' => '4111111111111111',
        ])->assertCreated();

        $reference = (string) $response->json('data.payment_reference');
        $this->assertStringNotContainsString('4111111111111111', $reference);
        $this->assertDatabaseMissing('orders', ['payment_reference' => '4111111111111111']);
    }
}
