<?php

namespace Database\Seeders;

use App\Models\Seller;
use Database\Seeders\Support\CatalogData;
use Illuminate\Database\Seeder;

/**
 * Seed the three sellers used by every generated offer.
 *
 * WHY: `name` is stable, so re-running the seeder updates rather than inserts.
 */
class SellerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogData::sellers() as $seller) {
            Seller::updateOrCreate(
                ['name' => $seller['name']],
                ['is_active' => true],
            );
        }
    }
}
