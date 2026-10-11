<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Turns validated list parameters into query conditions; sorting is whitelisted.
 */
class UserFilter
{
    public const SORTABLE = ['name', 'email', 'last_login_at', 'created_at'];

    /**
     * @param  array<string, mixed>  $params  validated request input
     */
    public function __construct(private readonly array $params) {}

    public function apply(Builder $query): Builder
    {
        $this->search($query, $this->params['search'] ?? null);

        if (isset($this->params['role'])) {
            $query->role($this->params['role']);
        }
        if (isset($this->params['status'])) {
            $query->where('is_active', $this->params['status'] === 'active');
        }

        $column = in_array($this->params['sort'] ?? null, self::SORTABLE, true) ? $this->params['sort'] : 'name';
        $direction = ($this->params['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($column, $direction)->orderBy('id', $direction);

        return $query;
    }

    /**
     * "Contains" match on name or email. The users table stays small (staff accounts,
     * not customers), so a FULLTEXT index would not pay for itself here.
     */
    private function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
    }
}
