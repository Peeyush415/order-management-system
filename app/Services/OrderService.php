<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\OrderActivityType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\OrderNotEditableException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly OrderPricingCalculator $calculator) {}

    /**
     * Creates the order, its item snapshots, the first status history row and the activity
     * entry in one transaction: either all of it exists, or none of it.
     * Stock is not touched here; it is deducted when the order is confirmed.
     *
     * @param  array<string, mixed>  $data  validated input
     *
     * @throws ProductUnavailableException
     */
    public function create(array $data, User $by): Order
    {
        return DB::transaction(function () use ($data, $by) {
            [$discountType, $discountValue] = $this->discount($data);
            $pricing = $this->price($data['items'], $discountType, $discountValue);
            $status = OrderStatus::from($data['status'] ?? OrderStatus::Pending->value);

            $order = new Order([
                'customer_id' => $data['customer_id'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'priority' => OrderPriority::from((int) ($data['priority'] ?? OrderPriority::Normal->value)),
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ]);
            // Set by the server only, never from input.
            $order->forceFill([
                'status' => $status,
                'created_by' => $by->id,
                'version' => 1,
                ...$this->totals($pricing),
            ])->save();

            // Derived from the id inside the transaction, so two requests can never share a number.
            // Saved quietly: it completes the creation, it is not an "update" for the audit log.
            $order->forceFill(['order_number' => Order::numberFor($order->id)])->saveQuietly();

            $this->insertItems($order, $pricing);

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $by->id,
            ]);
            $this->recordActivity($order, $by, OrderActivityType::Created, 'Order created as '.$status->label(), [
                'status' => $status->value,
                'grand_total' => $pricing['grand_total'],
            ]);

            if (! empty($data['note'])) {
                $order->notes()->create(['user_id' => $by->id, 'body' => $data['note']]);
                $this->recordActivity($order, $by, OrderActivityType::NoteAdded, 'Note added');
            }

            return $order;
        });
    }

    /**
     * Optimistic locking: the row is locked, then the version the client loaded must still be
     * the current one, or nothing is saved (409). Draft/pending orders can change everything
     * the user chooses; confirmed and later only the priority.
     *
     * @param  array<string, mixed>  $data  validated input, including version
     *
     * @throws ConcurrencyConflictException|OrderNotEditableException|ProductUnavailableException
     */
    public function update(Order $order, array $data, User $by): Order
    {
        return DB::transaction(function () use ($order, $data, $by) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->version !== (int) $data['version']) {
                throw new ConcurrencyConflictException;
            }
            if ($order->status->isTerminal()) {
                throw new OrderNotEditableException('Completed or cancelled orders cannot be edited.');
            }

            $order->priority = OrderPriority::from((int) ($data['priority'] ?? $order->priority->value));
            $itemsReplaced = false;

            if ($order->status->isEditable()) {
                [$discountType, $discountValue] = $this->discount($data);
                $pricing = $this->price($data['items'], $discountType, $discountValue);

                $order->fill([
                    'customer_id' => $data['customer_id'],
                    'order_date' => $data['order_date'] ?? $order->order_date->toDateString(),
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                ])->forceFill($this->totals($pricing));

                // Items are cheap to rewrite and are only editable before stock moves.
                $order->items()->delete();
                $this->insertItems($order, $pricing);
                $itemsReplaced = true;
            }

            $changed = array_keys($order->getDirty());
            if ($itemsReplaced) {
                $changed[] = 'items';
            }

            $order->version++;
            $order->save();

            $this->recordActivity($order, $by, OrderActivityType::Updated, 'Order updated', ['changed' => $changed]);

            return $order;
        });
    }

    /**
     * Assigns the order to a manager/staff member, or unassigns it with null.
     * The Auditable trait records this as "assignment_changed" with old and new ids.
     *
     * @throws OrderNotEditableException
     */
    public function assign(Order $order, ?int $assigneeId, User $by): Order
    {
        return DB::transaction(function () use ($order, $assigneeId, $by) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status->isTerminal()) {
                throw new OrderNotEditableException('Completed or cancelled orders cannot be reassigned.');
            }
            if ($order->assigned_to === $assigneeId) {
                return $order;
            }

            $previous = $order->assigned_to;
            $order->assigned_to = $assigneeId;
            $order->version++;
            $order->save();

            $assignee = $assigneeId ? User::query()->find($assigneeId, ['id', 'name']) : null;
            $this->recordActivity(
                $order,
                $by,
                OrderActivityType::Assigned,
                $assignee ? "Assigned to {$assignee->name}" : 'Unassigned',
                ['from' => $previous, 'to' => $assigneeId],
            );

            return $order;
        });
    }

    public function addNote(Order $order, string $body, User $by): OrderNote
    {
        return DB::transaction(function () use ($order, $body, $by) {
            $note = $order->notes()->create(['user_id' => $by->id, 'body' => $body]);
            $this->recordActivity($order, $by, OrderActivityType::NoteAdded, 'Note added', ['note_id' => $note->id]);

            return $note;
        });
    }

    /**
     * Soft delete, only for orders holding no deducted stock.
     *
     * @throws OrderNotEditableException
     */
    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->status->isDeletable()) {
                throw new OrderNotEditableException('Only draft, pending or cancelled orders can be deleted. Cancel the order first.');
            }

            $order->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?DiscountType, 1: int}
     */
    private function discount(array $data): array
    {
        $type = isset($data['discount_type']) ? DiscountType::from($data['discount_type']) : null;

        return [$type, $type ? (int) $data['discount_value'] : 0];
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function price(array $items, ?DiscountType $discountType, int $discountValue): array
    {
        return $this->calculator->calculate($this->snapshotLines($items), $discountType, $discountValue);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, int>
     */
    private function totals(array $pricing): array
    {
        return [
            'subtotal' => $pricing['subtotal'],
            'discount_total' => $pricing['discount_total'],
            'tax_total' => $pricing['tax_total'],
            'grand_total' => $pricing['grand_total'],
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function insertItems(Order $order, array $pricing): void
    {
        $now = now();

        OrderItem::insert(array_map(
            fn (array $line) => $line + ['order_id' => $order->id, 'created_at' => $now, 'updated_at' => $now],
            array_values($pricing['lines']),
        ));
    }

    private function recordActivity(Order $order, User $by, OrderActivityType $type, string $description, ?array $properties = null): void
    {
        $order->activities()->create([
            'user_id' => $by->id,
            'type' => $type,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    /**
     * Loads all products in one query and copies what the order must remember
     * (name, SKU, price, tax rate), so later product edits never change this order.
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array<string, mixed>>
     *
     * @throws ProductUnavailableException
     */
    private function snapshotLines(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get(['id', 'name', 'sku', 'price', 'tax_rate_bps', 'status'])
            ->keyBy('id');

        return array_map(function (array $item) use ($products) {
            $product = $products->get($item['product_id']);

            // Validation checked this, but the product may have changed since.
            if (! $product || $product->status !== ProductStatus::Active) {
                throw new ProductUnavailableException((int) $item['product_id']);
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $product->price,
                'tax_rate_bps' => $product->tax_rate_bps,
                'quantity' => (int) $item['quantity'],
            ];
        }, array_values($items));
    }
}
