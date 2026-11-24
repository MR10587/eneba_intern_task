<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The current user's cart DTO.
 *
 * WHY: total_cents is recomputed by CartService from persisted offer prices.
 * No client-supplied money value is ever echoed back.
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items' => CartItemResource::collection($this->resource->items)->resolve(),
            'total_cents' => (int) ($this->resource->total_cents ?? 0),
            'currency' => config('catalog.currency'),
        ];
    }
}
