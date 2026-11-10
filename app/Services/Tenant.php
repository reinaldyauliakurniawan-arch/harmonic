<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Organization;

class Tenant
{
    public ?Organization $organization = null;

    public ?Membership $membership = null;

    public function set(Organization $organization, ?Membership $membership): void
    {
        $this->organization = $organization;
        $this->membership = $membership;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function forget(): void
    {
        $this->organization = null;
        $this->membership = null;
    }
}
