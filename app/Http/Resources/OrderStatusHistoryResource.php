<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One timeline entry: previous status, new status, who, when, remarks.
 *
 * @mixin OrderStatusHistory
 */
class OrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from_status ? ['value' => $this->from_status->value, 'label' => $this->from_status->label()] : null,
            'to' => ['value' => $this->to_status->value, 'label' => $this->to_status->label()],
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? new UserSummaryResource($this->changedBy) : null),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
