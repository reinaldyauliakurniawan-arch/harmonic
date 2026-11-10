<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Access;

class TaskPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Task $t): bool
    {
        return $this->a->member();
    }

    public function create(User $u): bool
    {
        return $this->a->member();
    }

    public function update(User $u, Task $t): bool
    {
        return $this->a->owner($t->responsible_id) || $this->a->owner($t->accountable_id) || $this->a->owner($t->created_by) || $this->a->leads($t->team_seat_id);
    }

    public function status(User $u, Task $t): bool
    {
        return $this->a->owner($t->responsible_id) || $this->a->owner($t->accountable_id) || $this->a->leads($t->team_seat_id);
    }

    public function delete(User $u, Task $t): bool
    {
        return $this->a->owner($t->created_by) || $this->a->owner($t->accountable_id) || $this->a->leads($t->team_seat_id);
    }
}
