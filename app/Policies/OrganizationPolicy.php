<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\Access;

class OrganizationPolicy
{
    public function __construct(private Access $access) {}

    public function view(User $user, Organization $org): bool
    {
        return $this->access->member();
    }

    /** People, settings, activity: Org Admin only. */
    public function manage(User $user, Organization $org): bool
    {
        return $this->access->orgAdmin();
    }

    /** Leadership or Org Admin: opening cycles, org events, 360 setup. */
    public function lead(User $user, Organization $org): bool
    {
        return $this->access->leadership() || $this->access->orgAdmin();
    }

    public function leadership(User $user, Organization $org): bool
    {
        return $this->access->leadership();
    }
}
