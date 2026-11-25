<?php

namespace Database\Seeders;

use App\Models\Product;
use Database\Seeders\Support\CatalogData;
use Illuminate\Database\Seeder;

/**
 * Seed the catalog with a stable, reviewable set of games.
 *
 * WHY: `slug` is the natural key. Re-running updates the same rows instead of
 * creating duplicates, and no factory randomness leaks into the demo store.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogData::products() as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'name' => $product['name'],
                    'description' => sprintf(
                        '%s is available as an instant digital key for %s. Compare trusted seller offers and choose the region that fits you.',
                        $product['name'],
                        $product['platform'],
                    ),
                    'platform' => $product['platform'],
                    'category' => $product['category'],
                    'genre' => $product['genre'],
                    'release_date' => $product['release_date'],
                    'image_path' => null,
                    'popularity' => $product['popularity'],
                    'compare_at_price_cents' => $product['compare_at_price_cents'],
                    'is_active' => $product['is_active'],
                ],
            );
        }
    }
}
