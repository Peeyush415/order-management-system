<?php

namespace App\Http\Resources;

use App\Models\OrderNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderNote
 */
class OrderNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserSummaryResource($this->user) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
