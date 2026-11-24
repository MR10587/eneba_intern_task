<?php

namespace Tests\Feature\Api;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Feature tests for the signed-in order history endpoints.
 *
 * WHY: these pin the ownership invariant (a user only ever sees their own
 * orders) and the delivery contract (order detail exposes the purchased key).
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }

    /**
     * Build one completed order with one delivered item.
     *
     * @return array{order: Order, key: DigitalKey}
     */
    private function completedOrderFor(User $user, int $priceCents = 1999): array
    {
        $product = Product::factory()->create(['name' => 'Purchased Game']);
        $offer = Offer::factory()->for($product)->create(['price_cents' => $priceCents]);
        $key = DigitalKey::factory()->for($offer)->create();

        $order = Order::factory()->for($user)->create(['total_cents' => $priceCents]);
        OrderItem::factory()
            ->for($order)
            ->for($offer)
            ->create([
                'unit_price_cents' => $priceCents,
                'digital_key_id' => $key->id,
            ]);

        return ['order' => $order, 'key' => $key];
    }

    public function test_order_endpoints_require_authentication(): void
    {
        $this->getJson('/api/orders')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson('/api/orders/1')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_list_returns_only_own_orders_with_purchased_products_and_dates(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->completedOrderFor($user, 1999);
        $this->completedOrderFor($user, 3500);
        $this->completedOrderFor($other, 9999);

        $response = $this->withToken($token)->getJson('/api/orders?per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'status',
                        'total_cents',
                        'currency',
                        'payment_status',
                        'payment_reference',
                        'created_at',
                        'items' => [
                            '*' => [
                                'id',
                                'product_id',
                                'product' => ['id', 'slug', 'name'],
                                'quantity',
                                'unit_price_cents',
                                'line_total_cents',
                            ],
                        ],
                    ],
                ],
            ]);

        // Key codes are intentionally absent from list responses.
        $this->assertArrayNotHasKey('digital_key', $response->json('data.0.items.0'));

        $pageTwo = $this->withToken($token)->getJson('/api/orders?per_page=1&page=2');
        $pageTwo->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2);
    }

    public function test_detail_returns_the_delivered_key_and_purchase_date(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $fixture = $this->completedOrderFor($user);

        $this->withToken($token)->getJson('/api/orders/'.$fixture['order']->id)
            ->assertOk()
            ->assertJsonPath('data.id', $fixture['order']->id)
            ->assertJsonPath('data.order_id', $fixture['order']->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_cents', 1999)
            ->assertJsonPath('data.items.0.digital_key.code', $fixture['key']->code)
            ->assertJsonPath('data.items.0.product.name', 'Purchased Game')
            ->assertJsonStructure(['data' => ['created_at']]);
    }

    public function test_another_users_order_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $fixture = $this->completedOrderFor($owner);

        $this->withToken($this->tokenFor($owner))
            ->getJson('/api/orders/'.$fixture['order']->id)
            ->assertOk();

        // WHY: feature tests reuse one app instance, so RequestGuard caches the
        // user. Clear it when switching tokens (same pattern as CartTest).
        Auth::forgetGuards();

        $this->withToken($this->tokenFor($other))
            ->getJson('/api/orders/'.$fixture['order']->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_missing_order_and_invalid_pagination_are_reported(): void
    {
        $token = $this->tokenFor(User::factory()->create());

        $this->withToken($token)->getJson('/api/orders/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->withToken($token)->getJson('/api/orders?per_page=999')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['per_page']]);
    }
}
