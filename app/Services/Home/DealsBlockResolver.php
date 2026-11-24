<?php

namespace App\Services\Home;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `deals` homepage block.
 *
 * Invariant owned: a deal is an active product with a real purchasable price
 * and a higher comparison price. Discount percentages are derived by
 * ProductResource, never accepted from block config or computed in the client.
 */
class DealsBlockResolver implements HomeBlockResolver
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

        // WHY: min_price_cents and compare_at_price_cents must be compared in
        // an outer query, because the former is a computed alias in the inner
        // SELECT and cannot be used in that same SELECT's WHERE clause.
        $rows = DB::query()
            ->fromSub($base, 'products')
            ->whereNotNull('min_price_cents')
            ->whereNotNull('compare_at_price_cents')
            ->whereColumn('compare_at_price_cents', '>', 'min_price_cents')
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
        $default = (int) config('home.deals_limit');
        $value = $config['limit'] ?? $default;

        if (! is_numeric($value)) {
            $value = $default;
        }

        return max(1, min((int) $value, (int) config('home.max_items')));
    }
}
