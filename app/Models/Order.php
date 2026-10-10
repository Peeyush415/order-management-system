<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Only what a user may choose. Order number, status, totals, version, stock and
// lifecycle timestamps are set by the services, never from request input.
#[Fillable([
    'customer_id', 'assigned_to', 'priority', 'order_date',
    'discount_type', 'discount_value',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'priority' => OrderPriority::class,
            'discount_type' => DiscountType::class,
            'order_date' => 'date',
            'discount_value' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'version' => 'integer',
            'stock_deducted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Order number derived from the primary key, e.g. id 5 → ORD-10005.
     */
    public static function numberFor(int $id): string
    {
        return 'ORD-'.(10000 + $id);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest()->latest('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest()->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->latest()->latest('id');
    }
}
