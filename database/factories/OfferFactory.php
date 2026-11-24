<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'seller_id' => Seller::factory(),
            'region' => fake()->randomElement(['Europe', 'Global', 'US']),
            'price_cents' => fake()->numberBetween(100, 10000),
            'currency' => config('catalog.currency'),
            'is_active' => true,
        ];
    }

    /**
     * Inactive offers stay in the database but cannot be priced or bought.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
