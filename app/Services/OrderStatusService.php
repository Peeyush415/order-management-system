<?php

namespace App\Services;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Moves the order to a new status. The row is locked first, so two users pressing
     * "Confirm" at the same time can't both succeed: the second one sees the order already
     * confirmed, which is not a valid transition.
     *
     * Stock: deducted on confirm, put back on cancel only if it had been deducted.
     * Not enough stock throws and the whole change rolls back.
     *
     * @throws InvalidStatusTransitionException|InsufficientStockException
     */
    public function transition(Order $order, OrderStatus $to, User $by, ?string $remarks = null): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $remarks) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransitionException($from, $to);
            }

            $now = now();
            $stockActivity = null;

            if ($to === OrderStatus::Confirmed) {
                $this->inventory->deductForOrder($order, $by);
                $order->stock_deducted_at = $now;
                $stockActivity = [OrderActivityType::StockDeducted, 'Stock deducted'];
            } elseif ($to === OrderStatus::Cancelled && $order->stock_deducted_at !== null) {
                $this->inventory->restockForOrder($order, $by);
                $stockActivity = [OrderActivityType::Restocked, 'Stock returned to inventory'];
            }

            $order->status = $to;

            match ($to) {
                OrderStatus::Confirmed => $order->confirmed_at = $now,
                OrderStatus::Completed => $order->completed_at = $now,
                OrderStatus::Cancelled => $order->forceFill(['cancelled_at' => $now, 'cancellation_reason' => $remarks]),
                default => null,
            };

            // A status change is a change: clients editing an older version get 409.
            $order->version++;
            $order->save();

            $order->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $by->id,
                'remarks' => $remarks,
            ]);
            $order->activities()->create([
                'user_id' => $by->id,
                'type' => OrderActivityType::StatusChanged,
                'description' => "Status changed from {$from->label()} to {$to->label()}",
                'properties' => ['from' => $from->value, 'to' => $to->value],
            ]);

            // Notifications are queued listeners that only run once this transaction commits.
            OrderStatusChanged::dispatch($order, $from, $to, $by);

            if ($stockActivity) {
                $order->activities()->create([
                    'user_id' => $by->id,
                    'type' => $stockActivity[0],
                    'description' => $stockActivity[1],
                ]);
            }

            return $order;
        });
    }
}
