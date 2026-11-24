<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal seller representation embedded in product offers.
 *
 * Seller dashboards are out of scope, so only public identity is exposed.
 */
class SellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
