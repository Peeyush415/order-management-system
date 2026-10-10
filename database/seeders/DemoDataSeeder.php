<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderPricingCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo data: 100 customers, 50 products and 600 orders over the last 6 months.
 *
 * Every order is consistent: its status history follows a valid path, totals match
 * its items, and each product's stock equals the sum of its stock ledger rows.
 * Rows are built in memory and bulk-inserted (not via the services) for speed.
 * Model events are off: the seeder writes its own audit rows, so the Auditable trait must not.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const ORDER_COUNT = 600;

    private const CHUNK = 500;

    private const LOW_STOCK_PRODUCTS = 4;

    private const CANCEL_REASONS = [
        'Customer changed their mind',
        'Duplicate order',
        'Payment not received',
        'Customer requested cancellation',
        'Delivery address could not be confirmed',
    ];

    public function __construct(private readonly OrderPricingCalculator $calculator) {}

    private Carbon $now;

    private int $adminId;

    /** @var list<int> */
    private array $officeIds;

    /** @var list<int> */
    private array $staffIds;

    /** Rows to insert, keyed by table name. */
    private array $rows = [];

    /** Stock events per product: [time, quantity, order_id, user_id, type]. */
    private array $stockEvents = [];

    public function run(): void
    {
        $this->now = now()->startOfMinute();
        $this->adminId = User::role('admin')->value('id');
        $this->officeIds = User::role(['admin', 'manager'])->pluck('id')->all();
        $this->staffIds = User::role('staff')->pluck('id')->all();

        DB::transaction(function () {
            $customerIds = $this->seedCustomers();
            [$products, $lowStockIds] = $this->seedProducts();
            $this->buildOrders($customerIds, $products, $lowStockIds);
            $this->buildStockLedger(array_keys($products), $lowStockIds);
            $this->insertAll();
        });
    }

    /** @return list<int> ids of active customers */
    private function seedCustomers(): array
    {
        // Factory states rebind $this to the factory, so capture the ids first.
        $officeIds = $this->officeIds;
        $creator = fn () => ['created_by' => fake()->randomElement($officeIds)];

        Customer::factory()->count(90)->state($creator)->create();
        Customer::factory()->count(10)->inactive()->state($creator)->create();

        return Customer::where('status', 'active')->pluck('id')->all();
    }

    /** @return array{0: array<int, Product>, 1: list<int>} */
    private function seedProducts(): array
    {
        Product::factory()->count(50 - self::LOW_STOCK_PRODUCTS)->create();
        $lowStockIds = Product::factory()->count(self::LOW_STOCK_PRODUCTS)->create()->pluck('id')->all();

        return [Product::all()->keyBy('id')->all(), $lowStockIds];
    }

    private function buildOrders(array $customerIds, array $products, array $lowStockIds): void
    {
        // Sorted dates so order numbers grow with time.
        $starts = collect(range(1, self::ORDER_COUNT))
            ->map(fn () => $this->randomStart())
            ->sortBy(fn (Carbon $start) => $start->timestamp)
            ->values();

        $nextId = (int) Order::withTrashed()->max('id') + 1;
        $normalProducts = array_values(array_diff(array_keys($products), $lowStockIds));

        foreach ($starts as $start) {
            $this->buildOrder($nextId++, $start, $customerIds, $products, $normalProducts, $lowStockIds);
        }
    }

    private function buildOrder(int $id, Carbon $start, array $customerIds, array $products, array $normalProducts, array $lowStockIds): void
    {
        $path = $this->statusPath($start);
        $times = $this->stepTimes($start, count($path));
        $reachesConfirmed = in_array(OrderStatus::Confirmed, $path, true);
        $final = end($path);

        $creatorId = fake()->randomElement($this->officeIds);
        $assigneeId = match (true) {
            $reachesConfirmed => fake()->randomElement($this->staffIds),
            $final === OrderStatus::Pending && fake()->boolean() => fake()->randomElement($this->staffIds),
            default => null,
        };

        // Who made each step, decided once so history, audit and ledger agree.
        $actors = [];
        foreach ($path as $i => $to) {
            $actors[$i] = $i === 0 ? $creatorId : $this->changedBy($to, $path[$i - 1], $creatorId, $assigneeId);
        }

        // Low-stock products only appear on orders that never move stock.
        $pool = $reachesConfirmed ? $normalProducts : array_merge($normalProducts, $lowStockIds);
        $lines = collect(fake()->randomElements($pool, fake()->numberBetween(1, 5)))
            ->map(fn (int $productId) => ['product' => $products[$productId], 'quantity' => fake()->numberBetween(1, 5)])
            ->all();

        [$discountType, $discountValue] = $this->randomDiscount();
        $pricing = $this->price($lines, $discountType, $discountValue);

        $confirmedAt = $this->timeOf(OrderStatus::Confirmed, $path, $times);
        $cancelledAt = $this->timeOf(OrderStatus::Cancelled, $path, $times);

        $this->rows['orders'][] = [
            'id' => $id,
            'order_number' => Order::numberFor($id),
            'customer_id' => fake()->randomElement($customerIds),
            'assigned_to' => $assigneeId,
            'created_by' => $creatorId,
            'status' => $final->value,
            'priority' => fake()->randomElement([1, 2, 2, 2, 2, 3, 3, 4]),
            'order_date' => $start->toDateString(),
            'discount_type' => $discountType?->value,
            'discount_value' => $discountValue,
            'subtotal' => $pricing['subtotal'],
            'discount_total' => $pricing['discount_total'],
            'tax_total' => $pricing['tax_total'],
            'grand_total' => $pricing['grand_total'],
            'stock_deducted_at' => $confirmedAt,
            'confirmed_at' => $confirmedAt,
            'completed_at' => $this->timeOf(OrderStatus::Completed, $path, $times),
            'cancelled_at' => $cancelledAt,
            'cancellation_reason' => $cancelledAt ? fake()->randomElement(self::CANCEL_REASONS) : null,
            'version' => count($path),
            'created_at' => $times[0],
            'updated_at' => end($times),
            'deleted_at' => null,
        ];

        foreach ($pricing['lines'] as $line) {
            $this->rows['order_items'][] = ['order_id' => $id] + $line + ['created_at' => $times[0], 'updated_at' => $times[0]];
        }

        $this->buildTimeline($id, $path, $times, $actors, $creatorId, $assigneeId, $pricing);

        if ($reachesConfirmed) {
            $confirmedBy = $actors[array_search(OrderStatus::Confirmed, $path, true)];
            $cancelledBy = $cancelledAt ? $actors[array_search(OrderStatus::Cancelled, $path, true)] : null;

            foreach ($pricing['lines'] as $line) {
                $this->stockEvents[$line['product_id']][] = [$confirmedAt, -$line['quantity'], $id, $confirmedBy, StockMovementType::OrderDeduct];
                if ($cancelledAt) {
                    $this->stockEvents[$line['product_id']][] = [$cancelledAt, $line['quantity'], $id, $cancelledBy, StockMovementType::OrderRestock];
                }
            }
        }
    }

    /**
     * History, activities, audit rows and notes for one order.
     */
    private function buildTimeline(int $id, array $path, array $times, array $actors, int $creatorId, ?int $assigneeId, array $pricing): void
    {
        $morph = (new Order)->getMorphClass();
        $itemCount = count($pricing['lines']);

        foreach ($path as $i => $to) {
            $from = $path[$i - 1] ?? null;
            $by = $actors[$i];
            $at = $times[$i];

            $this->rows['order_status_histories'][] = [
                'order_id' => $id,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'changed_by' => $by,
                'remarks' => $to === OrderStatus::Cancelled ? 'Cancelled: see reason' : null,
                'created_at' => $at,
            ];

            $this->rows['order_activities'][] = $i === 0
                ? $this->activity($id, $by, OrderActivityType::Created, 'Order created as '.$to->label(), ['status' => $to->value], $at)
                : $this->activity($id, $by, OrderActivityType::StatusChanged, "Status changed from {$from->label()} to {$to->label()}", ['from' => $from->value, 'to' => $to->value], $at);

            $this->rows['audit_logs'][] = $i === 0
                ? $this->audit($id, $morph, $by, 'created', null, ['status' => $to->value, 'grand_total' => $pricing['grand_total']], $at)
                : $this->audit($id, $morph, $by, 'status_changed', ['status' => $from->value], ['status' => $to->value], $at);

            if ($to === OrderStatus::Confirmed) {
                $this->rows['order_activities'][] = $this->activity($id, $by, OrderActivityType::StockDeducted, "Stock deducted for {$itemCount} item(s)", null, $at);
            }
            if ($to === OrderStatus::Cancelled && in_array(OrderStatus::Confirmed, $path, true)) {
                $this->rows['order_activities'][] = $this->activity($id, $by, OrderActivityType::Restocked, "Stock restocked for {$itemCount} item(s)", null, $at);
            }
        }

        $authors = array_values(array_filter([$creatorId, $assigneeId]));
        $firstTime = Carbon::parse($times[0]);
        $lastTime = Carbon::parse(end($times));

        for ($n = fake()->numberBetween(0, 3); $n > 0; $n--) {
            $at = Carbon::createFromTimestamp(fake()->numberBetween($firstTime->timestamp, $lastTime->timestamp))->format('Y-m-d H:i:s');
            $by = fake()->randomElement($authors);

            $this->rows['order_notes'][] = ['order_id' => $id, 'user_id' => $by, 'body' => fake()->sentence(10), 'created_at' => $at, 'updated_at' => $at];
            $this->rows['order_activities'][] = $this->activity($id, $by, OrderActivityType::NoteAdded, 'Note added', null, $at);
        }
    }

    /**
     * Opening stock plus every order movement, in time order, with a running balance.
     * Opening stock is sized so the balance never goes below a target, so it can't go negative.
     */
    private function buildStockLedger(array $productIds, array $lowStockIds): void
    {
        $openingAt = $this->now->copy()->subMonths(6)->subDay()->format('Y-m-d H:i:s');

        foreach ($productIds as $productId) {
            $events = $this->stockEvents[$productId] ?? [];
            usort($events, fn ($a, $b) => $a[0] <=> $b[0]);

            $running = 0;
            $lowest = 0;
            foreach ($events as $event) {
                $running += $event[1];
                $lowest = min($lowest, $running);
            }

            $target = in_array($productId, $lowStockIds, true) ? fake()->numberBetween(0, 9) : fake()->numberBetween(20, 200);
            $balance = $target - $lowest;

            if ($balance > 0) {
                $this->rows['stock_movements'][] = $this->movement($productId, null, $this->adminId, StockMovementType::ManualAdjustment, $balance, $balance, 'Opening stock', $openingAt);
            }

            foreach ($events as [$at, $quantity, $orderId, $userId, $type]) {
                $balance += $quantity;
                $this->rows['stock_movements'][] = $this->movement($productId, $orderId, $userId, $type, $quantity, $balance, null, $at);
            }

            // Products are rewritten below with their final stock.
            $this->rows['product_stock'][$productId] = $balance;
        }
    }

    private function insertAll(): void
    {
        foreach (['orders', 'order_items', 'order_status_histories', 'order_notes', 'order_activities', 'audit_logs', 'stock_movements'] as $table) {
            foreach (array_chunk($this->rows[$table] ?? [], self::CHUNK) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        // stock_quantity is not fillable on purpose; set it directly from the ledger result.
        foreach ($this->rows['product_stock'] as $productId => $stock) {
            DB::table('products')->where('id', $productId)->update(['stock_quantity' => $stock]);
        }
    }

    /**
     * Snapshots each product and prices the order with the same calculator the order service uses.
     */
    private function price(array $lines, ?DiscountType $type, int $value): array
    {
        $snapshots = array_map(fn (array $line) => [
            'product_id' => $line['product']->id,
            'product_name' => $line['product']->name,
            'sku' => $line['product']->sku,
            'unit_price' => $line['product']->price,
            'tax_rate_bps' => $line['product']->tax_rate_bps,
            'quantity' => $line['quantity'],
        ], array_values($lines));

        return $this->calculator->calculate($snapshots, $type, $value);
    }

    /**
     * A valid path through the state machine. Recent orders are still in progress;
     * older ones are mostly completed or cancelled.
     *
     * @return list<OrderStatus>
     */
    private function statusPath(Carbon $start): array
    {
        $age = $start->diffInDays($this->now);
        $weights = match (true) {
            $age <= 3 => ['draft' => 15, 'pending' => 30, 'confirmed' => 25, 'processing' => 15, 'ready' => 10, 'cancelled' => 5],
            $age <= 30 => ['draft' => 5, 'pending' => 15, 'confirmed' => 15, 'processing' => 15, 'ready' => 10, 'completed' => 30, 'cancelled' => 10],
            default => ['draft' => 2, 'pending' => 5, 'completed' => 80, 'cancelled' => 13],
        };
        $final = OrderStatus::from($this->weighted($weights));

        $forward = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed];
        $prefix = fake()->boolean(30) ? [OrderStatus::Draft] : [];
        $upTo = fn (OrderStatus $s) => array_merge($prefix, array_slice($forward, 0, array_search($s, $forward, true) + 1));

        if ($final === OrderStatus::Draft) {
            return [OrderStatus::Draft];
        }

        if ($final !== OrderStatus::Cancelled) {
            return $upTo($final);
        }

        $cancelFrom = OrderStatus::from($this->weighted(['draft' => 15, 'pending' => 35, 'confirmed' => 20, 'processing' => 20, 'ready' => 10]));

        return $cancelFrom === OrderStatus::Draft
            ? [OrderStatus::Draft, OrderStatus::Cancelled]
            : [...$upTo($cancelFrom), OrderStatus::Cancelled];
    }

    /**
     * Who made a transition, following the permission matrix: staff move their own
     * orders forward, managers confirm and cancel, only an admin cancels a ready order.
     */
    private function changedBy(OrderStatus $to, OrderStatus $from, int $creatorId, ?int $assigneeId): int
    {
        return match (true) {
            $to === OrderStatus::Cancelled && $from === OrderStatus::Ready => $this->adminId,
            in_array($to, [OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed], true) && $assigneeId && fake()->boolean(70) => $assigneeId,
            default => $creatorId,
        };
    }

    /** @return list<string> one timestamp per step, increasing, never in the future */
    private function stepTimes(Carbon $start, int $steps): array
    {
        $available = max(1, (int) $start->diffInMinutes($this->now));
        $maxGap = max(1, min(30 * 60, intdiv($available, $steps + 1)));

        $time = $start->copy();
        $times = [$time->format('Y-m-d H:i:s')];
        for ($i = 1; $i < $steps; $i++) {
            $time->addMinutes(fake()->numberBetween(min(30, $maxGap), $maxGap));
            $times[] = $time->format('Y-m-d H:i:s');
        }

        return $times;
    }

    private function randomStart(): Carbon
    {
        $start = $this->now->copy()->subDays(fake()->numberBetween(0, 180))->setTime(fake()->numberBetween(9, 17), fake()->numberBetween(0, 59));

        return $start->greaterThan($this->now->copy()->subHour())
            ? $this->now->copy()->subMinutes(fake()->numberBetween(60, 240))
            : $start;
    }

    /** @return array{0: ?DiscountType, 1: int} */
    private function randomDiscount(): array
    {
        return match ($this->weighted(['none' => 70, 'fixed' => 15, 'percent' => 15])) {
            'fixed' => [DiscountType::Fixed, fake()->numberBetween(5, 50) * 100],
            'percent' => [DiscountType::Percent, fake()->randomElement([500, 1000, 1500])],
            default => [null, 0],
        };
    }

    private function timeOf(OrderStatus $status, array $path, array $times): ?string
    {
        $i = array_search($status, $path, true);

        return $i === false ? null : $times[$i];
    }

    private function weighted(array $weights): string
    {
        $roll = fake()->numberBetween(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function activity(int $orderId, ?int $userId, OrderActivityType $type, string $description, ?array $properties, string $at): array
    {
        return [
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type->value,
            'description' => $description,
            'properties' => $properties ? json_encode($properties) : null,
            'created_at' => $at,
        ];
    }

    private function audit(int $orderId, string $morph, int $userId, string $event, ?array $old, ?array $new, string $at): array
    {
        return [
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $morph,
            'auditable_id' => $orderId,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => $at,
        ];
    }

    private function movement(int $productId, ?int $orderId, ?int $userId, StockMovementType $type, int $quantity, int $balance, ?string $reason, string $at): array
    {
        return [
            'product_id' => $productId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type->value,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'reason' => $reason,
            'created_at' => $at,
        ];
    }
}
