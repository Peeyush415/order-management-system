<?php

namespace App\Models\Concerns;

use App\Support\CacheVersion;
use Illuminate\Support\Facades\DB;

/**
 * Any saved or deleted row of this model makes the cached dashboard figures stale.
 * The bump runs after the surrounding transaction commits, so a rolled-back change
 * never invalidates anything (and a change that did commit always does).
 */
trait BumpsDashboardCache
{
    public static function bootBumpsDashboardCache(): void
    {
        $bump = fn () => DB::afterCommit(fn () => CacheVersion::bump('dashboard'));

        static::saved($bump);
        static::deleted($bump);
    }
}
