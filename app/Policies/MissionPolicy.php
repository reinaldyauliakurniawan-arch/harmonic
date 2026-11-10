<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;
use App\Services\Access;

class MissionPolicy
{
    public function __construct(private Access $a) {}

    public function view(User $u, Mission $m): bool
    {
        return $this->a->member();
    }

    public function create(User $u, string $level, ?int $team, ?int $owner): bool
    {
        return match ($level) {
            'company' => $this->a->leadership(),
            'team' => $this->a->leads($team),
            'personal' => $owner !== null && ($this->a->owner($owner) || $this->a->aboveMember($owner)),
            default => false,
        };
    }

    public function update(User $u, Mission $m): bool
    {
        return $this->a->owner($m->owner_id) || $this->a->owner($m->created_by);
    }

    public function status(User $u, Mission $m): bool
    {
        return $this->a->owner($m->owner_id);
    }

    public function close(User $u, Mission $m): bool
    {
        return $this->update($u, $m) || $this->a->leads($m->team_seat_id) || ($m->level === 'company' && $this->a->leadership());
    }

    public function delete(User $u, Mission $m): bool
    {
        return $this->a->owner($m->created_by) || $this->a->leads($m->team_seat_id);
    }
}
