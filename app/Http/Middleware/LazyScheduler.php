<?php

namespace App\Http\Middleware;

use App\Services\Scheduler;
use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;

/** Stands in for cron: due jobs run after the response, at most once per 10 minutes per organization. */
class LazyScheduler
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($request->user() && ($org = app(Tenant::class)->organization)) {
            app(Scheduler::class)->runIfDue($org);
        }
    }
}
