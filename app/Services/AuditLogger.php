<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes one audit_logs row for a model. Used by the Auditable trait for model events,
 * and directly for changes that fire no model event (role and permission syncs).
 */
class AuditLogger
{
    public const EVENTS = [
        'created', 'updated', 'deleted', 'restored',
        'status_changed', 'assignment_changed', 'role_changed', 'password_changed', 'permissions_changed',
    ];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(Model $model, string $event, ?array $old, ?array $new): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
        ]);
    }
}
