<?php

namespace App\Http\Resources;

use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product DTO shared by the catalog list and the product detail endpoint.
 *
 * The list query returns plain rows with two computed columns
 * (min_price_cents, available_offer_count); the detail query returns a real
 * Product model with offers loaded. This resource handles both shapes so the
 * SPA receives exactly one product contract.
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $minPrice = $this->resource->min_price_cents ?? null;
        $compareAt = $this->resource->compare_at_price_cents ?? null;
        $releaseDate = $this->resource->release_date ?? null;

        // Offers are only included on the detail endpoint, never in the list.
        $hasOffers = $this->resource instanceof Product && $this->resource->relationLoaded('offers');

        return [
            'id' => (int) $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'platform' => $this->resource->platform,
            'genre' => $this->resource->genre,
            'release_date' => $releaseDate instanceof CarbonInterface
                ? $releaseDate->toDateString()
                : $releaseDate,
            'image_path' => $this->resource->image_path,
            'popularity' => (int) $this->resource->popularity,

            // WHY: null means "no purchasable offer right now". The product is
            // still visible, but checkout must reject it.
            'min_price_cents' => $minPrice !== null ? (int) $minPrice : null,
            'compare_at_price_cents' => $compareAt !== null ? (int) $compareAt : null,
            'discount_percent' => $this->discountPercent($minPrice, $compareAt),
            'currency' => config('catalog.currency'),
            'available' => $this->isAvailable(),
            'available_offer_count' => (int) ($this->resource->available_offer_count ?? 0),

            'offers' => $this->when(
                $hasOffers,
                fn () => OfferResource::collection($this->resource->offers),
            ),
        ];
    }

    /**
     * Deals are derived from persisted prices; the client never computes them.
     */
    private function discountPercent(mixed $minPrice, mixed $compareAt): ?int
    {
        if ($minPrice === null || $compareAt === null || (int) $compareAt <= (int) $minPrice) {
            return null;
        }

        return (int) round((((int) $compareAt - (int) $minPrice) / (int) $compareAt) * 100);
    }

    /**
     * Availability comes from the aggregated offer count in list responses.
     */
    private function isAvailable(): bool
    {
        $count = $this->resource->available_offer_count ?? null;

        return $count !== null && (int) $count > 0;
    }
}
