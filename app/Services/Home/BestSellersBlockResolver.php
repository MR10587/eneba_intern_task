<?php

namespace App\Services\Home;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogService;

/**
 * Resolves the `best_sellers` homepage block.
 *
 * Invariant owned: ranking and pricing come from persisted catalog data. The
 * configured limit is clamped server-side so a block cannot request the whole
 * products table.
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
        $query = Product::query()->where('products.is_active', true);
        $this->catalog->addComputedPriceColumns($query);

        $products = $query->orderByDesc('products.popularity')
            ->orderBy('products.slug')
            ->limit($this->limit($config))
            ->get();

        return ProductResource::collection($products)->resolve();
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
