<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Services\Access;

class MeetingPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Meeting $m): bool
    {
        return $this->a->member();
    }

    /** The team leader starts it; if the leader's seat is vacant, any team member can (ME-02). */
    public function start(User $u, int $team): bool
    {
        $f = $this->a->formation();
        if (! $f->isTeam($team)) {
            return false;
        }

        return $this->a->leads($team) || (! $f->seat($team)->holder_id && $this->a->inTeam($team));
    }

    public function participate(User $u, Meeting $m): bool
    {
        return $m->isLive() && $this->a->inTeam($m->team_seat_id);
    }

    public function facilitate(User $u, Meeting $m): bool
    {
        return $m->isLive() && $this->a->owner($m->facilitator_id);
    }

    public function editNotes(User $u, Meeting $m): bool
    {
        return $m->isLive() ? $this->participate($u, $m) : $this->a->leads($m->team_seat_id);
    }
}
