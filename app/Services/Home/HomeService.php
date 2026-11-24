<?php

namespace App\Services\Home;

use App\Models\HomepageBlock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the homepage block list.
 *
 * Invariants owned by this class:
 *  1. Only enabled blocks are returned.
 *  2. Order is deterministic: position, then id.
 *  3. Each block's `data` is resolved server-side from its own type/config.
 *  4. An unknown type degrades to an empty array instead of failing /home.
 */
class HomeService
{
    public function __construct(
        private readonly BannerBlockResolver $banner,
        private readonly BestSellersBlockResolver $bestSellers,
        private readonly DealsBlockResolver $deals,
    ) {}

    /**
     * @return Collection<int, HomepageBlock>
     */
    public function blocks(): Collection
    {
        $blocks = HomepageBlock::query()
            ->where('is_enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($blocks as $block) {
            $resolver = match ($block->type) {
                'banner' => $this->banner,
                'best_sellers' => $this->bestSellers,
                'deals' => $this->deals,
                default => null,
            };

            $config = is_array($block->config) ? $block->config : [];

            // WHY: set a transient attribute so HomeBlockResource can serialise
            // every type with one uniform shape ({key, title, type, position, data}).
            $block->setAttribute('resolved_data', $resolver?->resolve($config) ?? []);
        }

        return $blocks;
    }
}
