<?php

namespace App\Services\Home;

use App\Http\Resources\ProductResource;
use App\Services\CatalogService;

/**
 * Resolves the `banner` homepage block.
 *
 * Invariant owned: a stale or inactive configured product must not break the
 * homepage. The copy still comes from the DB block config; only the product is
 * looked up, and it is always an active catalog summary.
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

        // WHY: no hardcoded fallback slug. If configuration is stale, the
        // block degrades to "product": null instead of showing another game.
        $product = is_string($slug) && $slug !== ''
            ? $this->catalog->findActiveSummaryBySlug($slug)
            : null;

        return [
            'headline' => $this->text($config, 'headline'),
            'subheadline' => $this->text($config, 'subheadline'),
            'cta_label' => $this->text($config, 'cta_label'),
            'cta_href' => $this->text($config, 'cta_href'),
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
