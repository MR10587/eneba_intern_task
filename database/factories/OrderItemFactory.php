<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'offer_id' => Offer::factory(),
            'quantity' => 1,
            'unit_price_cents' => 1999,
            // Null is intentional: the unique index permits many NULLs, so
            // factory rows coexist until a test assigns a real delivered key.
            'digital_key_id' => null,
        ];
    }
}
