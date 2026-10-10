<?php

namespace App\Models;

use App\Enums\OrderActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline entry, written by the activity listener.
 */
#[Fillable(['user_id', 'type', 'description', 'properties'])]
class OrderActivity extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => OrderActivityType::class,
            'properties' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
