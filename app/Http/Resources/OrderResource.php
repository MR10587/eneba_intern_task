<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One completed order, used by checkout and order history alike.
 *
 * WHY: `order_id` is kept as an alias of `id` so the documented checkout
 * contract and the normal resource convention are both satisfied. Items are
 * only emitted when loaded, keeping this resource reusable for future
 * summaries without changing its shape.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'order_id' => (int) $this->resource->id,
            'status' => $this->resource->status,
            'total_cents' => (int) $this->resource->total_cents,
            // WHY: read the persisted currency, not the current config, so an
            // old order always reports exactly what the buyer paid.
            'currency' => $this->resource->currency,
            'payment_status' => $this->resource->payment_status,
            'payment_reference' => $this->resource->payment_reference,
            'created_at' => $this->resource->created_at,
            'items' => $this->when(
                $this->resource->relationLoaded('items'),
                fn () => OrderItemResource::collection($this->resource->items)->resolve(),
            ),
        ];
    }
}
