<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Models\Role;

/**
 * Append-only: written by the Auditable trait, never updated or deleted.
 */
#[Fillable([
    'user_id', 'event', 'auditable_type', 'auditable_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /** Short names the API uses for the audited models. */
    public const SUBJECT_TYPES = [
        'order' => Order::class,
        'customer' => Customer::class,
        'product' => Product::class,
        'user' => User::class,
        'role' => Role::class,
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Eager loads who did it and what it was done to, including soft-deleted records,
     * so a deleted order still shows its number.
     */
    public function scopeWithSubject(Builder $query): void
    {
        $withTrashed = fn (Builder $q) => $q->withTrashed();

        $query->with([
            'user:id,name',
            'auditable' => fn (MorphTo $morph) => $morph->constrain([
                Order::class => $withTrashed,
                Customer::class => $withTrashed,
                Product::class => $withTrashed,
                User::class => $withTrashed,
            ]),
        ]);
    }

    public function subjectType(): string
    {
        return array_search($this->auditable_type, self::SUBJECT_TYPES, true) ?: class_basename($this->auditable_type);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
