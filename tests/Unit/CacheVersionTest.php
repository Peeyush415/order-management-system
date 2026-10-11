<?php

namespace Tests\Unit;

use App\Support\CacheVersion;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheVersionTest extends TestCase
{
    public function test_keys_include_the_current_version(): void
    {
        $this->assertSame('dashboard:v1:summary:2026-10-01', CacheVersion::key('dashboard', 'summary', '2026-10-01'));

        CacheVersion::bump('dashboard');

        $this->assertSame('dashboard:v2:summary:2026-10-01', CacheVersion::key('dashboard', 'summary', '2026-10-01'));
    }

    public function test_bumping_makes_old_entries_unreachable(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            return ++$calls;
        };

        $this->assertSame(1, CacheVersion::remember('dashboard', ['x'], 300, $compute));
        $this->assertSame(1, CacheVersion::remember('dashboard', ['x'], 300, $compute)); // cached

        CacheVersion::bump('dashboard');

        $this->assertSame(2, CacheVersion::remember('dashboard', ['x'], 300, $compute)); // recomputed
    }

    public function test_versions_are_independent_per_name(): void
    {
        CacheVersion::bump('dashboard');

        $this->assertSame(1, CacheVersion::current('lookups'));
        $this->assertTrue(Cache::has('cache-version:dashboard'));
    }
}
