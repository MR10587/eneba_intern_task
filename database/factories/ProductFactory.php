<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'platform' => fake()->randomElement(['Steam', 'Epic Games', 'Xbox', 'PlayStation']),
            'genre' => fake()->randomElement(['Action', 'Sandbox', 'RPG', 'Strategy']),
            'release_date' => fake()->date(),
            'image_path' => null,
            'popularity' => fake()->numberBetween(0, 1000),
            'compare_at_price_cents' => null,
            'is_active' => true,
        ];
    }

    /**
     * Hidden products must never appear in the catalog.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * A deal has a higher comparison price than its offers.
     */
    public function deal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'compare_at_price_cents' => 9999,
        ]);
    }
}
