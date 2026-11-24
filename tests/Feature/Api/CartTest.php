<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Feature tests for /api/cart/*.
 *
 * WHY: these pin the three security/business invariants that matter most —
 * only the authenticated user's cart is reachable, all money comes from the
 * persisted offer, and stale items are reported instead of silently changed.
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a product with one purchasable offer at a known price.
     *
     * @param  array<string, mixed>  $productAttributes
     * @return array{product: Product, offer: Offer, key: DigitalKey}
     */
    private function purchasableProduct(array $productAttributes = [], int $priceCents = 1999): array
    {
        $product = Product::factory()->create($productAttributes);
        $offer = Offer::factory()->for($product)->create(['price_cents' => $priceCents]);
        $key = DigitalKey::factory()->for($offer)->create();

        return ['product' => $product, 'offer' => $offer, 'key' => $key];
    }

    /**
     * Authenticate as a factory user and return its Sanctum token.
     */
    private function tokenFor(?User $user = null): string
    {
        $user ??= User::factory()->create();

        return $user->createToken('api')->plainTextToken;
    }

    public function test_all_cart_endpoints_require_authentication(): void
    {
        $this->getJson('/api/cart')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/cart/items', ['product_id' => 1, 'offer_id' => 1])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/cart/items/1')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_empty_cart_returns_zero_totals(): void
    {
        $this->withToken($this->tokenFor())
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.total_cents', 0)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('meta.item_count', 0)
            ->assertJsonPath('meta.available_item_count', 0);
    }

    public function test_add_creates_cart_and_uses_persisted_offer_price(): void
    {
        $fixture = $this->purchasableProduct(['name' => 'Cart Game'], 1999);
        $token = $this->tokenFor();

        $response = $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.product_id', $fixture['product']->id)
            ->assertJsonPath('data.items.0.offer_id', $fixture['offer']->id)
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.line_total_cents', 1999)
            ->assertJsonPath('data.items.0.available', true)
            ->assertJsonPath('data.total_cents', 1999)
            ->assertJsonPath('meta.item_count', 1);

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'offer_id' => $fixture['offer']->id,
            'quantity' => 1,
        ]);
    }

    public function test_duplicate_add_is_idempotent(): void
    {
        $fixture = $this->purchasableProduct([], 2500);
        $token = $this->tokenFor();
        $payload = [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
        ];

        $this->withToken($token)->postJson('/api/cart/items', $payload)->assertCreated();

        $this->withToken($token)->postJson('/api/cart/items', $payload)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total_cents', 2500)
            ->assertJsonPath('meta.item_count', 1);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(1, (int) CartItem::first()->quantity);
    }

    public function test_add_rejects_missing_or_inactive_product(): void
    {
        $fixture = $this->purchasableProduct(['is_active' => false]);
        $token = $this->tokenFor();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => 999999,
            'offer_id' => $fixture['offer']->id,
        ])->assertNotFound()->assertJsonPath('code', 'PRODUCT_NOT_FOUND');

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
        ])->assertNotFound()->assertJsonPath('code', 'PRODUCT_NOT_FOUND');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_add_rejects_missing_or_wrong_product_offer(): void
    {
        $fixture = $this->purchasableProduct();
        $other = $this->purchasableProduct();
        $token = $this->tokenFor();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => 999999,
        ])->assertNotFound()->assertJsonPath('code', 'OFFER_NOT_FOUND');

        // The offer exists, but belongs to a different product.
        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $other['offer']->id,
        ])->assertNotFound()->assertJsonPath('code', 'OFFER_NOT_FOUND');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_add_rejects_sold_out_and_inactive_offers(): void
    {
        $soldOut = $this->purchasableProduct();
        $soldOut['key']->update(['status' => 'sold']);

        $inactive = $this->purchasableProduct();
        $inactive['offer']->update(['is_active' => false]);

        $token = $this->tokenFor();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $soldOut['product']->id,
            'offer_id' => $soldOut['offer']->id,
        ])->assertStatus(409)->assertJsonPath('code', 'INSUFFICIENT_INVENTORY');

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $inactive['product']->id,
            'offer_id' => $inactive['offer']->id,
        ])->assertStatus(409)->assertJsonPath('code', 'INSUFFICIENT_INVENTORY');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_users_see_and_delete_only_their_own_cart_items(): void
    {
        $fixture = $this->purchasableProduct([], 4200);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerToken = $this->tokenFor($owner);
        $otherToken = $this->tokenFor($other);

        $this->withToken($ownerToken)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
        ])->assertCreated();

        // WHY: Laravel's RequestGuard caches the resolved user for the life of
        // the app instance, and feature tests reuse it across requests. Real
        // HTTP requests get a fresh app, so clear the guards when switching
        // users — see the same pattern in AuthTest::test_logout_deletes_only_...
        Auth::forgetGuards();

        $this->withToken($otherToken)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->withToken($otherToken)->deleteJson('/api/cart/items/'.$fixture['offer']->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        // The owner's item survived the other user's delete attempt.
        Auth::forgetGuards();

        $this->withToken($ownerToken)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total_cents', 4200);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_delete_removes_own_item_and_missing_item_returns_not_found(): void
    {
        $fixture = $this->purchasableProduct([], 1500);
        $token = $this->tokenFor();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
        ])->assertCreated();

        $this->withToken($token)->deleteJson('/api/cart/items/'.$fixture['offer']->id)
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.total_cents', 0);

        $this->withToken($token)->deleteJson('/api/cart/items/'.$fixture['offer']->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_stale_items_remain_visible_but_are_flagged_unavailable(): void
    {
        $soldOut = $this->purchasableProduct(['name' => 'Sold Out Later'], 1000);
        $deactivated = $this->purchasableProduct(['name' => 'Deactivated Later'], 2000);
        $token = $this->tokenFor();

        foreach ([$soldOut, $deactivated] as $fixture) {
            $this->withToken($token)->postJson('/api/cart/items', [
                'product_id' => $fixture['product']->id,
                'offer_id' => $fixture['offer']->id,
            ])->assertCreated();
        }

        $soldOut['key']->update(['status' => 'sold']);
        $deactivated['offer']->update(['is_active' => false]);

        $response = $this->withToken($token)->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('meta.item_count', 2)
            ->assertJsonPath('meta.available_item_count', 0)
            ->assertJsonPath('data.total_cents', 3000);

        $items = collect($response->json('data.items'))->keyBy('offer_id');

        $this->assertFalse($items->get($soldOut['offer']->id)['available']);
        $this->assertSame(1000, $items->get($soldOut['offer']->id)['line_total_cents']);
        $this->assertFalse($items->get($soldOut['offer']->id)['offer']['available']);

        $this->assertFalse($items->get($deactivated['offer']->id)['available']);
        $this->assertSame(2000, $items->get($deactivated['offer']->id)['line_total_cents']);
        $this->assertFalse($items->get($deactivated['offer']->id)['offer']['available']);
    }

    public function test_client_supplied_money_and_quantity_fields_are_ignored(): void
    {
        $fixture = $this->purchasableProduct([], 7777);
        $token = $this->tokenFor();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $fixture['product']->id,
            'offer_id' => $fixture['offer']->id,
            'price_cents' => 1,
            'total_cents' => 1,
            'quantity' => 99,
        ])->assertCreated()
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.line_total_cents', 7777)
            ->assertJsonPath('data.total_cents', 7777);

        $this->assertDatabaseHas('cart_items', [
            'offer_id' => $fixture['offer']->id,
            'quantity' => 1,
        ]);
    }
}
