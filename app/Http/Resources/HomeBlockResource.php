<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uniform DTO for every homepage block.
 *
 * WHY: the SPA renders generically by `type`. It receives `data` already
 * resolved by the backend and never needs to know which endpoint or table
 * produced that data.
 */
class HomeBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'title' => $this->resource->title,
            'type' => $this->resource->type,
            'position' => (int) $this->resource->position,
            'data' => $this->resource->resolved_data ?? [],
        ];
    }
}
