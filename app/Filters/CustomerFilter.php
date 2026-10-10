<?php

namespace App\Filters;

use App\Filters\Concerns\SearchesText;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns validated list parameters into query conditions. Each parameter has one method;
 * sorting only accepts whitelisted columns, so ORDER BY can never be injected.
 */
class CustomerFilter
{
    use SearchesText;

    public const SORTABLE = ['name', 'email', 'created_at'];

    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        $this->searchText($query, $this->params['search'] ?? null, ['name', 'email', 'company'], ['name', 'email']);
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
