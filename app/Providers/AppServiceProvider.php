<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Any lazy-loaded relation (an N+1 query) throws outside production, so it is caught in dev and tests.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Role is spatie's model, so its policy can't be found by naming convention.
        Gate::policy(Role::class, RolePolicy::class);

        // 5 login attempts per minute per email + IP: stops password guessing on one
        // account without locking out other users who share the same IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }
}
