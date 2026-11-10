<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;

/** Every role predicate from PRD section 5, computed per request from Winning Formation. */
class Access
{
    private ?Formation $formation = null;

    public function __construct(private Tenant $tenant) {}

    public function formation(): Formation
    {
        return $this->formation ??= Formation::load();
    }

    /** Drop the memoized tree after seats change. */
    public function refresh(): void
    {
        $this->formation = null;
    }

    public function me(): ?Membership
    {
        return $this->tenant->membership;
    }

    public function id(): ?int
    {
        return $this->tenant->membership?->id;
    }

    public function superAdmin(?User $user): bool
    {
        return (bool) $user?->is_super_admin;
    }

    public function member(?Membership $m = null, ?Organization $org = null): bool
    {
        $m ??= $this->me();
        $org ??= $this->tenant->organization;

        return $m && $org && $m->organization_id === $org->id && $m->status === 'active' && $org->status === 'active';
    }

    public function orgAdmin(): bool
    {
        return $this->member() && $this->me()->is_org_admin;
    }

    public function leadership(): bool
    {
        return $this->member() && $this->formation()->isLeadership($this->id());
    }

    public function leads(?int $teamSeatId): bool
    {
        return $this->member() && $this->formation()->leads($this->id(), $teamSeatId);
    }

    public function inTeam(?int $teamSeatId): bool
    {
        return $this->member() && $this->formation()->inTeam($this->id(), $teamSeatId);
    }

    public function above(?int $seatId): bool
    {
        return $this->member() && $this->formation()->above($this->id(), $seatId);
    }

    public function aboveMember(int $subjectId): bool
    {
        return $this->member() && $this->formation()->aboveMember($this->id(), $subjectId);
    }

    public function owner(?int $ownerMemberId): bool
    {
        return $this->member() && $ownerMemberId !== null && $ownerMemberId === $this->id();
    }

    /** Leader of the team or Org Admin: the common "manage a team's things" right. */
    public function leadsOrAdmin(?int $teamSeatId): bool
    {
        return $this->leads($teamSeatId) || $this->orgAdmin();
    }

    /** May see judgment-of-people data about this subject (Evaluator, 360 results). */
    public function seesPerson(int $subjectId): bool
    {
        return $this->owner($subjectId) || $this->aboveMember($subjectId) || $this->orgAdmin();
    }
}
