<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One seller offer shown on a product page.
 *
 * Available offers always include inactive/stock-out entries with
 * available: false, so the SPA can display them and the buyer can still
 * understand why a cheaper-looking offer cannot be chosen.
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // CatalogService attaches this count with withCount(). WHY: availability
        // is a persisted fact (digital_keys.status), never client-supplied.
        $availableKeyCount = (int) ($this->resource->available_key_count ?? 0);

        return [
            'id' => (int) $this->resource->id,
            'seller' => new SellerResource($this->resource->seller),
            'region' => $this->resource->region,
            'price_cents' => (int) $this->resource->price_cents,
            'currency' => config('catalog.currency'),
            // WHY: an offer deactivated after it was added to a cart still has
            // available keys. Availability must also require is_active so a
            // stale cart line is never advertised as purchasable.
            'available' => (bool) $this->resource->is_active && $availableKeyCount > 0,
        ];
    }
}
