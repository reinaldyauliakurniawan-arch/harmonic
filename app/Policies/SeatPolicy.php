<?php

namespace App\Policies;

use App\Models\Seat;
use App\Models\User;
use App\Services\Access;

class SeatPolicy
{
    public function __construct(private Access $a) {}

    public function viewAny(User $u): bool
    {
        return $this->a->member();
    }

    public function manage(User $u, ?Seat $s = null): bool
    {
        return $this->a->orgAdmin();
    }

    /** Team cadence and team settings: the team's leader. */
    public function lead(User $u, Seat $s): bool
    {
        return $this->a->leads($s->id);
    }
}
