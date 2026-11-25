<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Seller;
use Database\Seeders\Support\CatalogData;
use Illuminate\Database\Seeder;

/**
 * Seed seller offers from the shared catalog definition.
 *
 * WHY: the database unique key is (product_id, seller_id, region), so that
 * triple is also the updateOrCreate key. Prices are always stored in EUR cents
 * and are never accepted from the client.
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $productIds = Product::query()->pluck('id', 'slug');
        $sellerIds = Seller::query()->pluck('id', 'name');

        foreach (CatalogData::offers() as $offer) {
            Offer::updateOrCreate(
                [
                    'product_id' => (int) $productIds[$offer['product_slug']],
                    'seller_id' => (int) $sellerIds[$offer['seller_name']],
                    'region' => $offer['region'],
                ],
                [
                    'price_cents' => $offer['price_cents'],
                    'currency' => config('catalog.currency'),
                    'is_active' => $offer['is_active'],
                ],
            );
        }
    }
}
