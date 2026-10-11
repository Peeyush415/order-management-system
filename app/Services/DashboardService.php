<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\CacheVersion;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Dashboard figures from the database, never hard-coded.
 *
 * Revenue means booked revenue: the grand total of orders that are confirmed, processing,
 * ready or completed. Draft and pending orders aren't sales yet; cancelled ones aren't sales.
 * Average order value = revenue ÷ number of those orders.
 *
 * Order aggregates filter on order_date and read status and grand_total, which the
 * (order_date, status, grand_total) index covers. Results are cached for 5 minutes under
 * versioned keys; any order/customer/product change bumps the version.
 */
class DashboardService
{
    private const TTL = 300;

    public const BOOKED = [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed];

    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CacheVersion::remember('dashboard', ['summary', $from->toDateString(), $to->toDateString()], self::TTL, function () use ($from, $to) {
            $query = Order::query()->toBase()->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])->selectRaw('COUNT(*) as total_orders');
            foreach (OrderStatus::cases() as $status) {
                $query->selectRaw("COALESCE(SUM(status = ?), 0) as {$status->value}", [$status->value]);
            }
            $query->selectRaw('COALESCE(SUM(status IN ('.$this->placeholders().')), 0) as booked_orders', $this->bookedValues())
                ->selectRaw('COALESCE(SUM(CASE WHEN status IN ('.$this->placeholders().') THEN grand_total ELSE 0 END), 0) as revenue', $this->bookedValues());

            $row = (array) $query->first();
            $revenue = (int) $row['revenue'];
            $booked = (int) $row['booked_orders'];
            // Rounded to the nearest cent, in integers.
            $average = $booked ? intdiv($revenue * 2 + $booked, $booked * 2) : 0;

            return [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'total_orders' => (int) $row['total_orders'],
                'orders_by_status' => array_combine(
                    array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
                    array_map(fn (OrderStatus $s) => (int) $row[$s->value], OrderStatus::cases()),
                ),
                'booked_orders' => $booked,
                'revenue' => $revenue,
                'revenue_formatted' => Money::format($revenue),
                'average_order_value' => $average,
                'average_order_value_formatted' => Money::format($average),
                'total_customers' => Customer::query()->count(),
                'total_products' => Product::query()->count(),
            ];
        });
    }

    /**
     * Booked revenue per day, week (Monday start) or month. Periods without orders are
     * returned as zero so a chart has no gaps.
     *
     * @return list<array{period_start: string, label: string, revenue: int, revenue_formatted: string, orders: int}>
     */
    public function revenueTrend(string $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CacheVersion::remember('dashboard', ['trend', $period, $from->toDateString(), $to->toDateString()], self::TTL, function () use ($period, $from, $to) {
            // Fixed SQL per period from a whitelist; no user input reaches the expression.
            $bucket = match ($period) {
                'day' => 'order_date',
                'week' => 'DATE_SUB(order_date, INTERVAL WEEKDAY(order_date) DAY)',
                'month' => "DATE_FORMAT(order_date, '%Y-%m-01')",
            };

            $rows = Order::query()->toBase()
                ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('status', $this->bookedValues())
                ->selectRaw("DATE({$bucket}) as bucket, SUM(grand_total) as revenue, COUNT(*) as orders")
                ->groupBy('bucket')
                ->get()
                ->keyBy(fn ($row) => (string) $row->bucket);

            $start = match ($period) {
                'day' => $from,
                'week' => $from->startOfWeek(),
                'month' => $from->startOfMonth(),
            };

            $points = [];
            foreach (CarbonPeriod::create($start, "1 {$period}", $to) as $date) {
                $key = $date->toDateString();
                $revenue = (int) ($rows[$key]->revenue ?? 0);
                $points[] = [
                    'period_start' => $key,
                    'label' => match ($period) {
                        'day' => $date->format('d M'),
                        'week' => 'Week of '.$date->format('d M'),
                        'month' => $date->format('M Y'),
                    },
                    'revenue' => $revenue,
                    'revenue_formatted' => Money::format($revenue),
                    'orders' => (int) ($rows[$key]->orders ?? 0),
                ];
            }

            return $points;
        });
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    public function statusDistribution(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CacheVersion::remember('dashboard', ['status', $from->toDateString(), $to->toDateString()], self::TTL, function () use ($from, $to) {
            $counts = Order::query()->toBase()
                ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return array_map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($counts[$s->value] ?? 0),
            ], OrderStatus::cases());
        });
    }

    /**
     * A staff member's own workload. Not cached: one indexed query on assigned_to.
     */
    public function myWork(User $user): array
    {
        $counts = Order::query()->toBase()
            ->where('assigned_to', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = array_combine(
            array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            array_map(fn (OrderStatus $s) => (int) ($counts[$s->value] ?? 0), OrderStatus::cases()),
        );
        $open = array_sum(array_map(fn (OrderStatus $s) => $s->isTerminal() ? 0 : $byStatus[$s->value], OrderStatus::cases()));

        return ['open_orders' => $open, 'orders_by_status' => $byStatus];
    }

    private function bookedValues(): array
    {
        return array_map(fn (OrderStatus $s) => $s->value, self::BOOKED);
    }

    private function placeholders(): string
    {
        return implode(', ', array_fill(0, count(self::BOOKED), '?'));
    }
}
