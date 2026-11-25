<?php

namespace App\Services\Home;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `best_sellers` homepage block.
 *
 * Invariant owned: ranking and pricing come from persisted catalog data, and
 * only products with a purchasable price appear. The configured limit is
 * clamped server-side so a block cannot request the whole products table.
 */
class BestSellersBlockResolver implements HomeBlockResolver
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>
     */
    public function resolve(array $config): array
    {
        $base = Product::query()->where('products.is_active', true);
        $this->catalog->addComputedPriceColumns($base);

        // min_price_cents is a computed alias, so the availability check must
        // run in the outer query. Sold-out products never enter a storefront grid.
        $rows = DB::query()
            ->fromSub($base, 'products')
            ->whereNotNull('min_price_cents')
            ->orderByDesc('popularity')
            ->orderBy('slug')
            ->limit($this->limit($config))
            ->get();

        return ProductResource::collection($rows)->resolve();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function limit(array $config): int
    {
        $default = (int) config('home.best_sellers_limit');
        $value = $config['limit'] ?? $default;

        if (! is_numeric($value)) {
            $value = $default;
        }

        return max(1, min((int) $value, (int) config('home.max_items')));
    }
}
