<?php

namespace App\Policies;

use App\Models\Problem;
use App\Models\User;
use App\Services\Access;

class ProblemPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Problem $p): bool
    {
        return $this->a->member();
    }

    public function create(User $u): bool
    {
        return $this->a->member();
    }

    public function update(User $u, Problem $p): bool
    {
        return $this->a->inTeam($p->team_seat_id) || $this->a->owner($p->owner_id);
    }

    /** Ranking, horizon, moving, dropping, duplicates: team members (P-03, P-04). */
    public function maintain(User $u, Problem $p): bool
    {
        return $this->a->inTeam($p->team_seat_id);
    }

    public function solve(User $u, Problem $p): bool
    {
        return $this->a->inTeam($p->team_seat_id);
    }
}
