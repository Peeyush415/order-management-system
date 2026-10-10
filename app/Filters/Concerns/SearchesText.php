<?php

namespace App\Filters\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared text search: MySQL FULLTEXT in boolean prefix mode, with a prefix LIKE
 * fallback for words shorter than InnoDB's minimum token size.
 */
trait SearchesText
{
    /** Shorter words are ignored by InnoDB FULLTEXT (innodb_ft_min_token_size = 3). */
    private int $minFullTextLength = 3;

    /**
     * @param  list<string>  $fullTextColumns  columns of one FULLTEXT index
     * @param  list<string>  $prefixColumns  indexed columns for the short-input fallback
     */
    protected function searchText(Builder $query, ?string $term, array $fullTextColumns, array $prefixColumns): void
    {
        // Drop FULLTEXT boolean operators so user input can't change the query's meaning.
        $words = array_values(array_filter(
            preg_split('/\s+/', preg_replace('/[+\-<>()~*"@]+/', ' ', (string) $term)),
            fn (string $word) => $word !== '',
        ));

        if ($words === []) {
            return;
        }

        if (min(array_map('mb_strlen', $words)) >= $this->minFullTextLength) {
            // Every word must match, as a prefix: "ram sha" finds "Ram Sharma".
            $query->whereFullText($fullTextColumns, implode(' ', array_map(fn ($w) => "+{$w}*", $words)), ['mode' => 'boolean']);

            return;
        }

        // Very short input: prefix match, which can still use an index (no leading %).
        $prefix = addcslashes(implode(' ', $words), '%_\\').'%';
        $query->where(function (Builder $q) use ($prefixColumns, $prefix) {
            foreach ($prefixColumns as $column) {
                $q->orWhere($column, 'like', $prefix);
            }
        });
    }
}
