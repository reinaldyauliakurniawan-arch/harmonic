<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use App\Services\Access;

class BoardPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Board $b): bool
    {
        return $this->a->member();
    }

    public function create(User $u): bool
    {
        return $this->a->member();
    }

    /** Columns and archiving: the board's creator or the leader of its team. */
    public function update(User $u, Board $b): bool
    {
        return $this->a->owner($b->created_by) || $this->a->leads($b->team_seat_id);
    }
}
