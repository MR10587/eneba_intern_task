<?php

namespace Database\Factories;

use App\Models\HomepageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HomepageBlock>
 */
class HomepageBlockFactory extends Factory
{
    protected $model = HomepageBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'block-'.Str::slug(fake()->unique()->words(2, true))
                .'-'.fake()->unique()->numberBetween(1, 99999),
            'title' => ucfirst(fake()->words(3, true)),
            'type' => fake()->randomElement(['banner', 'best_sellers', 'deals']),
            'config' => [],
            'position' => fake()->numberBetween(0, 10),
            'is_enabled' => true,
        ];
    }

    /**
     * Disabled blocks must never appear on /home.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['is_enabled' => false]);
    }

    /**
     * Pin the type so resolver-specific tests stay readable.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type]);
    }
}
