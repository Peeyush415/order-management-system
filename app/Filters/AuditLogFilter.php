<?php

namespace App\Filters;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Each filter matches one of the audit_logs indexes: (user_id, created_at),
 * (event, created_at) and (auditable_type, auditable_id, created_at).
 */
class AuditLogFilter
{
    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        if (isset($this->params['user_id'])) {
            $query->where('user_id', $this->params['user_id']);
        }
        if (isset($this->params['event'])) {
            $query->where('event', $this->params['event']);
        }
        if (isset($this->params['auditable_type'])) {
            $query->where('auditable_type', AuditLog::SUBJECT_TYPES[$this->params['auditable_type']]);

            if (isset($this->params['auditable_id'])) {
                $query->where('auditable_id', $this->params['auditable_id']);
            }
        }
        if (isset($this->params['from'])) {
            $query->where('created_at', '>=', $this->params['from'].' 00:00:00');
        }
        if (isset($this->params['to'])) {
            $query->where('created_at', '<=', $this->params['to'].' 23:59:59');
        }

        // Newest first; id breaks ties between rows written in the same second.
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
