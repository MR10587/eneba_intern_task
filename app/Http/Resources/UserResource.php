<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of the signed-in user.
 *
 * WHY: an explicit allow-list is safer than returning the model. It guarantees
 * that `password`, `remember_token` and token records never reach the client,
 * even if columns are added to the users table later.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            // Email verification is out of scope, so this stays null for new
            // users. The field is kept so the SPA has one stable user shape.
            'email_verified_at' => $this->resource->email_verified_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
