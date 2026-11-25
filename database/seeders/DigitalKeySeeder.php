<?php

namespace Database\Seeders;

use App\Models\DigitalKey;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Seller;
use Database\Seeders\Support\CatalogData;
use Illuminate\Database\Seeder;

/**
 * Seed deterministic digital keys for every generated offer.
 *
 * WHY: key codes are stable so seeding twice does not create duplicates. A key
 * already assigned to an order is never reset to `available`, even when the
 * seeders are re-run against a store that has completed checkouts.
 */
class DigitalKeySeeder extends Seeder
{
    public function run(): void
    {
        $productIds = Product::query()->pluck('id', 'slug');
        $sellerIds = Seller::query()->pluck('id', 'name');

        foreach (CatalogData::offers() as $definition) {
            $offer = Offer::query()
                ->where('product_id', (int) $productIds[$definition['product_slug']])
                ->where('seller_id', (int) $sellerIds[$definition['seller_name']])
                ->where('region', $definition['region'])
                ->firstOrFail();

            foreach (CatalogData::keyCodes($definition) as $code) {
                $key = DigitalKey::firstOrCreate(
                    ['code' => $code],
                    [
                        'offer_id' => $offer->id,
                        'status' => $definition['key_status'],
                    ],
                );

                // WHY: preserve the business history of purchased keys while
                // still making unused keys deterministic on every re-seed.
                if (! $key->wasRecentlyCreated && ! $key->orderItem()->exists()) {
                    $key->update([
                        'offer_id' => $offer->id,
                        'status' => $definition['key_status'],
                    ]);
                }
            }
        }
    }
}
