<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One purchased offer inside an order.
 *
 * WHY: the product summary is deliberately compact and the unit price is the
 * persisted `unit_price_cents`, not today's catalog price — the receipt must
 * reflect what was actually paid. The delivered key is only exposed when its
 * relation is loaded (detail/checkout), never in list responses.
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource->offer;
        $product = $offer?->product;

        return [
            'id' => (int) $this->resource->id,
            'offer_id' => (int) $this->resource->offer_id,
            'product_id' => $offer !== null ? (int) $offer->product_id : null,
            'product' => $product !== null ? [
                'id' => (int) $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
            ] : null,
            'region' => $offer?->region,
            // Always 1 in the MVP; returned for display only.
            'quantity' => 1,
            'unit_price_cents' => (int) $this->resource->unit_price_cents,
            'line_total_cents' => (int) $this->resource->unit_price_cents,
            'digital_key' => $this->when(
                $this->resource->relationLoaded('digitalKey'),
                fn () => $this->resource->digitalKey !== null
                    ? ['code' => $this->resource->digitalKey->code]
                    : null,
            ),
        ];
    }
}
