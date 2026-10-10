<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Turns validated list parameters into query conditions. Each parameter has one method;
 * sorting only accepts whitelisted columns, so ORDER BY can never be injected.
 */
class CustomerFilter
{
    public const SORTABLE = ['name', 'email', 'created_at'];

    /** Shorter words are ignored by InnoDB FULLTEXT (innodb_ft_min_token_size = 3). */
    private const MIN_FULLTEXT_LENGTH = 3;

    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        $this->search($query, $this->params['search'] ?? null);
        $this->status($query, $this->params['status'] ?? null);
        $this->sort($query, $this->params['sort'] ?? 'created_at', $this->params['direction'] ?? 'desc');

        return $query;
    }

    private function search(Builder $query, ?string $term): void
    {
        // Drop FULLTEXT boolean operators so user input can't change the query's meaning.
        $words = array_values(array_filter(
            preg_split('/\s+/', preg_replace('/[+\-<>()~*"@]+/', ' ', (string) $term)),
            fn (string $word) => $word !== '',
        ));

        if ($words === []) {
            return;
        }

        if (min(array_map('mb_strlen', $words)) >= self::MIN_FULLTEXT_LENGTH) {
            // Every word must match, as a prefix: "ram sha" finds "Ram Sharma".
            $query->whereFullText(['name', 'email', 'company'], implode(' ', array_map(fn ($w) => "+{$w}*", $words)), ['mode' => 'boolean']);

            return;
        }

        // Very short input: prefix match, which can still use an index (no leading %).
        $prefix = addcslashes(implode(' ', $words), '%_\\').'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $prefix)->orWhere('email', 'like', $prefix));
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
