<?php

namespace App\Http\Resources;

use App\Models\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the order's activity feed.
 *
 * @mixin OrderActivity
 */
class OrderActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'description' => $this->description,
            'properties' => $this->properties,
            // Null for system actions.
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserSummaryResource($this->user) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
