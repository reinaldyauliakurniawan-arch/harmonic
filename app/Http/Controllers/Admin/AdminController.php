<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SchedulerRun;
use App\Models\User;
use App\Services\Jobs;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/** Super Admin console (PRD F-09). It never opens a tenant's operational data. */
class AdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $orgs = Organization::query()
            ->when(in_array($status, ['pending', 'active', 'suspended', 'deleted'], true), fn ($q) => $q->where('status', $status))
            ->withCount(['memberships as members_count' => fn ($q) => $q->where('status', 'active')])
            ->addSelect(['last_activity' => DB::table('activity_log')->selectRaw('max(created_at)')->whereColumn('organization_id', 'organizations.id')])
            ->orderByRaw("status = 'pending' desc")->latest()->paginate(25)->withQueryString();

        return view('admin.index', compact('orgs', 'status'));
    }

    public function store(Request $request, Onboarding $onboarding)
    {
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'email' => 'required|email|max:190']);
        [$org, , $root] = $onboarding->createOrganization($data['name'], 'active', 'invite');
        [, $token] = $onboarding->invite($org, $data['email'], true, $root->id, null);

        return back()->with('link', route('invitations.show', $token))->with('link_for', $data['email'])->with('status', 'Organization created');
    }

    public function transition(Request $request, Organization $organization, string $action)
    {
        $allowed = [
            'approve' => ['pending'], 'reject' => ['pending'], 'suspend' => ['active'],
            'reactivate' => ['suspended'], 'delete' => ['active', 'suspended', 'pending'], 'restore' => ['deleted'],
        ];
        abort_unless(isset($allowed[$action]) && in_array($organization->status, $allowed[$action], true), 404);

        if ($action === 'reject') {
            app(Jobs::class)->purge($organization);

            return back()->with('status', 'Organization rejected');
        }
        $organization->status = match ($action) {
            'approve', 'reactivate', 'restore' => 'active',
            'suspend' => 'suspended',
            'delete' => 'deleted',
        };
        if ($action === 'approve') {
            $organization->approved_at = now();
        }
        $organization->purge_after = $action === 'delete' ? now()->addDays(30)->toDateString() : null;
        $organization->save();

        return back()->with('status', ['approve' => 'Approved', 'suspend' => 'Suspended', 'reactivate' => 'Reactivated', 'delete' => 'Deleted. Restorable for 30 days', 'restore' => 'Restored'][$action]);
    }

    public function resetLink(Request $request)
    {
        $email = strtolower($request->validate(['email' => 'required|email'])['email']);
        $user = User::where('email', $email)->whereNull('anonymized_at')->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No account uses this email.'])->withInput();
        }

        return back()->with('link', route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]))->with('link_for', $user->email);
    }

    public function maintenance()
    {
        Artisan::call('migrate:status', ['--pending' => true]);
        $pending = collect(explode("\n", Artisan::output()))->filter(fn ($l) => str_contains($l, 'Pending'))->map(fn ($l) => trim(preg_replace('/\.{2,}.*$/', '', $l)))->values();
        $runs = SchedulerRun::orderBy('job')->get();
        $orgNames = Organization::whereIn('id', $runs->pluck('organization_id')->filter())->pluck('name', 'id');

        return view('admin.maintenance', ['pending' => $pending, 'runs' => $runs, 'orgNames' => $orgNames, 'php' => PHP_VERSION]);
    }

    public function migrate()
    {
        Artisan::call('migrate', ['--force' => true]);

        return back()->with('status', 'Migrations ran')->with('output', Artisan::output());
    }

    public function clearCaches()
    {
        Artisan::call('optimize:clear');

        return back()->with('status', 'Caches cleared');
    }
}
