<?php

namespace Database\Factories;

use App\Models\DigitalKey;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalKey>
 */
class DigitalKeyFactory extends Factory
{
    protected $model = DigitalKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'code' => 'KEY-'.Str::upper(Str::random(12)),
            'status' => 'available',
        ];
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'sold']);
    }
}
