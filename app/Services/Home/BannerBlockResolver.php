<?php

namespace App\Services\Home;

use App\Http\Resources\ProductResource;
use App\Services\CatalogService;

/**
 * Resolves the `banner` homepage block.
 *
 * Invariant owned: a featured game must be purchasable. A stale, inactive, or
 * sold-out configured product is replaced by the most popular available product;
 * if none exists, the banner carries no product or CTA so the SPA hides it.
 * Copy still comes from the DB block config.
 */
class BannerBlockResolver implements HomeBlockResolver
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function resolve(array $config): array
    {
        $slug = $config['product_slug'] ?? null;
        $product = $this->catalog->findFeaturedProduct(is_string($slug) ? $slug : null);

        return [
            'headline' => $this->text($config, 'headline'),
            'subheadline' => $this->text($config, 'subheadline'),
            'cta_label' => $product !== null ? $this->text($config, 'cta_label') : null,
            // WHY: the CTA must follow the available product, not the stale
            // configured slug, so a sold-out game is never recommended.
            'cta_href' => $product !== null ? '/products/'.$product->slug : null,
            'product' => $product !== null ? (new ProductResource($product))->resolve() : null,
        ];
    }

    /**
     * Config values are JSON from the DB, so never assume their types.
     *
     * @param  array<string, mixed>  $config
     */
    private function text(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
