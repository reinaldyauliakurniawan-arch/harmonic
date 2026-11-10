<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Organization;
use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Resolves /o/{organization} and sets the tenant; non-members get 404 so tenants stay hidden. */
class EnsureMember
{
    public function __construct(private Tenant $tenant) {}

    public function handle(Request $request, Closure $next)
    {
        $param = $request->route('organization');
        $org = $param instanceof Organization ? $param : Organization::where('slug', $param)->first();
        abort_if(! $org || $org->status === 'deleted' || ! $request->user(), 404);

        $membership = Membership::withoutGlobalScope('organization')->with('user')
            ->where('organization_id', $org->id)->where('user_id', $request->user()->id)
            ->where('status', 'active')->first();
        abort_unless($membership, 404);

        if ($org->status !== 'active') {
            return response()->view('org.unavailable', ['organization' => $org], 403);
        }

        $this->tenant->set($org, $membership);
        // Controllers get the organization by type-hint; dropping the route parameter keeps
        // the remaining route models in their positions.
        $request->route()->forgetParameter('organization');
        app()->instance(Organization::class, $org);
        URL::defaults(['organization' => $org->slug, 'organization:slug' => $org->slug]);
        $request->session()->put('last_org', $org->slug);

        return $next($request);
    }
}
