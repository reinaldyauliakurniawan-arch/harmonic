<?php

namespace Tests;

use App\Models\Membership;
use App\Models\Organization;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected ?Organization $testOrg = null;

    protected ?Membership $testMember = null;

    /** Test code runs as this tenant between requests; every request starts with a clean container scope. */
    public function useTenant(Organization $org, ?Membership $m = null): void
    {
        $this->testOrg = $org;
        $this->testMember = $m;
        $this->restoreTenant();
    }

    private function restoreTenant(): void
    {
        $this->app->forgetScopedInstances();
        if ($this->testOrg) {
            app(Tenant::class)->set($this->testOrg, $this->testMember);
            URL::defaults(['organization' => $this->testOrg->slug, 'organization:slug' => $this->testOrg->slug]);
        }
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();
        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->restoreTenant();
        }
    }
}
