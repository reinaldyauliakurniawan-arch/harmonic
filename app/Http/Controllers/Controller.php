<?php

namespace App\Http\Controllers;

use App\Services\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cookie;

abstract class Controller
{
    use AuthorizesRequests;

    /** The team a person last picked on any team-scoped page, so Metrics, Problems, Meetings and Tasks open on it. */
    protected function pickTeam(mixed $asked, array $teams, ?int $fallback): ?int
    {
        $key = 'team_'.app(Tenant::class)->organization->id;
        if ($asked && isset($teams[(int) $asked])) {
            Cookie::queue($key, (string) (int) $asked, 60 * 24 * 365);

            return (int) $asked;
        }
        $saved = (int) request()->cookie($key);

        return isset($teams[$saved]) ? $saved : $fallback;
    }
}
