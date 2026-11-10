<?php

namespace App\Providers;

use App\Services\Access;
use App\Services\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenant::class);
        $this->app->scoped(Access::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Paginator::defaultView('components.pagination');

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('signup', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
    }
}
