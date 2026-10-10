<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes an audit_logs row on create, update, delete and restore, with only the
 * changed fields (raw old and new values). Model events run inside the caller's
 * transaction, so an audit row can never outlive a change that was rolled back.
 *
 * Not covered: query-builder updates (e.g. stock decrements), which fire no model
 * events; those are recorded in their own ledger instead.
 */
trait Auditable
{
    /** Changes that are noise in an audit trail. */
    private array $auditIgnored = ['created_at', 'updated_at', 'deleted_at', 'version'];

    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->writeAudit('created', null, $model->auditable($model->getAttributes())));

        static::updated(function (self $model) {
            $new = $model->auditable($model->getChanges());
            if ($new === []) {
                return;
            }

            $old = array_intersect_key($model->getRawOriginal(), $new);
            $model->writeAudit($model->auditEvent($new), $old, $new);
        });

        static::deleted(fn (self $model) => $model->writeAudit('deleted', $model->auditable($model->getRawOriginal()), null));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $model) => $model->writeAudit('restored', null, null));
        }
    }

    /**
     * Event name for an update; models override this to name important changes.
     *
     * @param  array<string, mixed>  $changes
     */
    protected function auditEvent(array $changes): string
    {
        return 'updated';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(array $attributes): array
    {
        // Hidden attributes (passwords, tokens) never reach the audit log.
        return Arr::except($attributes, [...$this->auditIgnored, ...$this->getHidden()]);
    }

    private function writeAudit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
        ]);
    }
}
