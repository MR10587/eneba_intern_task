<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'completed',
            'total_cents' => 1999,
            'currency' => config('catalog.currency'),
            'payment_status' => 'succeeded',
            'payment_reference' => 'SIM-'.Str::upper(Str::random(10)),
        ];
    }
}
