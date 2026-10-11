<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Support\AuditValueFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One audit row in the shape of the assessment example:
 * user, action, record, changed fields (old → new), time.
 * Needs the user and auditable relations loaded (AuditLog::withSubject()).
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function __construct(AuditLog $resource, private readonly AuditValueFormatter $formatter)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource->subjectType();

        return [
            'id' => $this->id,
            'event' => $this->event,
            'event_label' => Str::of($this->event)->replace('_', ' ')->ucfirst()->toString(),
            'user' => $this->user ? new UserSummaryResource($this->user) : null,
            'subject' => [
                'type' => $type,
                'type_label' => ucfirst($type),
                'id' => $this->auditable_id,
                'label' => $this->subjectLabel(),
            ],
            'changes' => $this->formatter->changes($this->resource),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function subjectLabel(): string
    {
        $subject = $this->auditable;

        return match (true) {
            $subject instanceof Order => $subject->order_number ?? "#{$subject->id}",
            $subject instanceof Product => "{$subject->name} ({$subject->sku})",
            $subject !== null && isset($subject->name) => (string) $subject->name,
            default => "#{$this->auditable_id}",
        };
    }
}
