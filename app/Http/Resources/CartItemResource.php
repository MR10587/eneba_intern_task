<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One cart line.
 *
 * WHY: line totals and availability come from CartService, which reads
 * persisted offer prices and key status. The client only displays them.
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource->offer->product;

        return [
            'id' => (int) $this->resource->id,
            'product_id' => (int) $this->resource->offer->product_id,
            'offer_id' => (int) $this->resource->offer_id,
            // Always 1 in the MVP; returned for display only. There is no
            // endpoint that can change it.
            'quantity' => 1,
            'product' => $product !== null ? (new ProductResource($product))->resolve() : null,
            'offer' => (new OfferResource($this->resource->offer))->resolve(),
            'line_total_cents' => (int) $this->resource->line_total_cents,
            'currency' => config('catalog.currency'),
            'available' => (bool) $this->resource->available,
        ];
    }
}
