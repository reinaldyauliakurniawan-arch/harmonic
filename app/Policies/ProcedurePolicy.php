<?php

namespace App\Policies;

use App\Models\Procedure;
use App\Models\User;
use App\Services\Access;

class ProcedurePolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Procedure $p): bool
    {
        return $this->a->member();
    }

    public function create(User $u, ?int $team): bool
    {
        return $this->a->formation()->isTeam($team) && ($this->a->leads($team) || $this->a->orgAdmin());
    }

    private function ownerHolder(Procedure $p): bool
    {
        return $this->a->owner($this->a->formation()->seat($p->owner_seat_id)?->holder_id);
    }

    public function update(User $u, Procedure $p): bool
    {
        return $this->ownerHolder($p) || $this->a->leads($p->team_seat_id);
    }

    public function archive(User $u, Procedure $p): bool
    {
        return $this->a->leads($p->team_seat_id);
    }

    /** SY-01: the owning team's leader or the owner seat's holder records audits. */
    public function audit(User $u, Procedure $p): bool
    {
        return $p->version > 0 && ($this->ownerHolder($p) || $this->a->leads($p->team_seat_id));
    }
}
