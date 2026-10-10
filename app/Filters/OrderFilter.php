<?php

namespace App\Filters;

use App\Enums\OrderStatus;
use App\Filters\Concerns\SearchesText;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Order list parameters → query. Each search path is chosen to use an index at 1M orders:
 * order number → primary key, customer → FULLTEXT, product → FULLTEXT + EXISTS on
 * order_items(product_id, order_id), assignee → prefix LIKE on the small users table.
 */
class OrderFilter
{
    use SearchesText;

    public const SORTABLE = ['order_date', 'grand_total', 'status', 'priority', 'customer'];

    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        $this->search($query, trim((string) ($this->params['search'] ?? '')));

        if (! empty($this->params['status'])) {
            $query->whereIn('status', $this->params['status']);
        }
        if (! empty($this->params['priority'])) {
            $query->whereIn('priority', array_map('intval', $this->params['priority']));
        }
        if (! empty($this->params['customer_id'])) {
            $query->where('customer_id', $this->params['customer_id']);
        }
        if (! empty($this->params['assigned_to'])) {
            $query->where('assigned_to', $this->params['assigned_to']);
        }
        // Plain comparisons on the DATE column (no DATE() wrapper) keep the index usable.
        if (! empty($this->params['date_from'])) {
            $query->where('order_date', '>=', $this->params['date_from']);
        }
        if (! empty($this->params['date_to'])) {
            $query->where('order_date', '<=', $this->params['date_to']);
        }

        $this->sort($query, $this->params['sort'] ?? 'order_date', $this->params['direction'] ?? 'desc');

        return $query;
    }

    private function search(Builder $query, string $term): void
    {
        // Ignore input made only of FULLTEXT operators.
        if (trim(preg_replace('/[+\-<>()~*"@]+/', ' ', $term)) === '') {
            return;
        }

        // "ORD-10005" or "10005" → primary key lookup, the fastest possible search.
        if (preg_match('/^(?:ORD-?)?(\d+)$/i', $term, $match) && (int) $match[1] > 10000) {
            $query->whereKey((int) $match[1] - 10000);

            return;
        }

        $customers = Customer::query()->select('id');
        $this->searchText($customers, $term, ['name', 'email', 'company'], ['name', 'email']);

        $products = Product::query()->select('id');
        $this->searchText($products, $term, ['name', 'sku'], ['sku', 'name']);

        $assignees = User::query()->select('id')->where('name', 'like', addcslashes($term, '%_\\').'%');

        $query->where(fn (Builder $q) => $q
            ->whereIn('customer_id', $customers)
            ->orWhereIn('assigned_to', $assignees)
            ->orWhereExists(fn (QueryBuilder $items) => $items
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereIn('order_items.product_id', $products)));
    }

    private function sort(Builder $query, string $column, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match ($column) {
            // Sort by customer name without a join; withTrashed so deleted customers still sort.
            'customer' => $query->orderBy(
                Customer::withTrashed()->select('name')->whereColumn('customers.id', 'orders.customer_id'),
                $direction,
            ),
            // Workflow order (draft → … → cancelled), not alphabetical.
            'status' => $query->orderByRaw(
                'FIELD(status, '.implode(', ', array_fill(0, count(OrderStatus::cases()), '?')).') '.$direction,
                array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
            ),
            'grand_total', 'priority' => $query->orderBy($column, $direction),
            default => $query->orderBy('order_date', $direction),
        };

        // id as a tie-breaker keeps pagination stable when values are equal.
        $query->orderBy((new Order)->qualifyColumn('id'), $direction);
    }
}
