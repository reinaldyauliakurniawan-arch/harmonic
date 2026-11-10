<?php

namespace App\Policies;

use App\Models\Metric;
use App\Models\User;
use App\Services\Access;

class MetricPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Metric $m): bool
    {
        return $this->a->member();
    }

    public function create(User $u, ?int $team): bool
    {
        return $this->a->leads($team) || ($this->a->orgAdmin() && $this->a->formation()->isTeam($team));
    }

    public function update(User $u, Metric $m): bool
    {
        return $this->a->leads($m->team_seat_id);
    }

    /** Week-level limits live in Metrics::canEnter. */
    public function enter(User $u, Metric $m): bool
    {
        return $this->a->leads($m->team_seat_id) || $this->a->owner($this->a->formation()->seat($m->owner_seat_id)?->holder_id);
    }
}
