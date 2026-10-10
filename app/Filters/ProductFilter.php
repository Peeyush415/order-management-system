<?php

namespace App\Filters;

use App\Filters\Concerns\SearchesText;
use Illuminate\Database\Eloquent\Builder;

/**
 * Validated list parameters → query. Sorting only accepts whitelisted columns.
 */
class ProductFilter
{
    use SearchesText;

    public const SORTABLE = ['name', 'sku', 'price', 'stock_quantity', 'created_at'];

    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        $this->searchText($query, $this->params['search'] ?? null, ['name', 'sku'], ['sku', 'name']);
        $this->status($query, $this->params['status'] ?? null);
        $this->sort($query, $this->params['sort'] ?? 'created_at', $this->params['direction'] ?? 'desc');

        return $query;
    }

    private function status(Builder $query, ?string $status): void
    {
        if ($status !== null) {
            $query->where('status', $status);
        }
    }

    private function sort(Builder $query, string $column, string $direction): void
    {
        $column = in_array($column, self::SORTABLE, true) ? $column : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        // id as a tie-breaker keeps pagination stable when values are equal.
        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }
}
