<?php

use App\Models\Event;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Problem;
use App\Models\Seat;
use App\Models\Task;
use App\Models\User;
use App\Services\Access;
use App\Services\Onboarding;
use App\Services\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/** An active organization whose admin holds the root seat. Sets the tenant for test code. */
function makeOrg(string $name = 'Acme', array $attrs = []): array
{
    $user = User::create(['name' => 'Ada Admin', 'email' => strtolower(str_replace(' ', '', $name)).'-admin-'.uniqid().'@example.com', 'password' => 'password123']);
    [$org, $m, $root] = app(Onboarding::class)->createOrganization($name, 'active', 'invite', $user);
    $org->forceFill(array_merge(['quarter_start_date' => '2026-10-05'], $attrs))->save();
    test()->useTenant($org, $m);

    return [$org, $m, $root];
}

function makeMember(string $name = 'Ben Member', bool $admin = false): Membership
{
    $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com', 'password' => 'password123']);
    $m = new Membership;
    $m->user_id = $user->id;
    $m->status = 'active';
    $m->is_org_admin = $admin;
    $m->joined_at = now();
    $m->save();

    return $m->load('user');
}

function makeSeat(string $title, ?Seat $parent = null, ?Membership $holder = null, array $extra = []): Seat
{
    $s = new Seat(['title' => $title, 'responsibilities' => []]);
    $s->parent_id = $parent?->id;
    $s->holder_id = $holder?->id;
    $s->forceFill($extra);
    $s->save();
    app(Access::class)->refresh();

    return $s;
}

/** Act as a member: signs in their user and makes test code run as them. */
function actAs(Membership $m)
{
    $org = Organization::find($m->organization_id);
    test()->useTenant($org, $m);
    // AuthenticateSession would sign out a different user who reuses this test session.
    session()->forget('password_hash_web');

    return test()->actingAs($m->user);
}

function ou(string $name, array $params = []): string
{
    return route('org.'.$name, $params);
}

/** CEO (admin) > Sales Lead (lead) > Rep (rep); CEO > Ops Lead (ops). */
function orgTree(): array
{
    [$org, $admin, $root] = makeOrg();
    $lead = makeMember('Lena Lead');
    $rep = makeMember('Rio Rep');
    $ops = makeMember('Oli Ops');
    $sales = makeSeat('Sales Lead', $root, $lead);
    $repSeat = makeSeat('Rep', $sales, $rep);
    $opsSeat = makeSeat('Ops Lead', $root, $ops);

    return compact('org', 'admin', 'root', 'lead', 'rep', 'ops', 'sales', 'repSeat', 'opsSeat');
}

function mkTask(array $attrs): Task
{
    $t = new Task(['title' => $attrs['title'] ?? 'Task', 'output' => 'Out', 'due_date' => $attrs['due_date'] ?? now()->addWeek()->toDateString(), 'team_seat_id' => $attrs['team_seat_id'] ?? null]);
    $t->forceFill(['responsible_id' => $attrs['r'], 'accountable_id' => $attrs['a'] ?? $attrs['r'], 'created_by' => $attrs['by'] ?? $attrs['r'], 'status' => $attrs['status'] ?? 'todo'])->save();

    return $t;
}

function mkMission(Membership $owner, ?int $team = null, string $status = 'on_track'): Mission
{
    $m = new Mission(['level' => $team ? 'team' : 'personal', 'team_seat_id' => $team, 'title' => 'Mission x', 'done_definition' => 'It is done when done', 'quarter_start' => '2026-10-05']);
    $m->forceFill(['owner_id' => $owner->id, 'created_by' => $owner->id, 'status' => $status])->save();

    return $m;
}

function mkProblem(Membership $owner, int $team, string $status = 'open'): Problem
{
    $p = new Problem(['team_seat_id' => $team, 'title' => 'Problem x']);
    $p->forceFill(['raised_by' => $owner->id, 'owner_id' => $owner->id, 'status' => $status])->save();

    return $p;
}

function mkEvent(Membership $by, string $scope = 'org', array $extra = []): Event
{
    $e = new Event(array_merge(['title' => 'Ev', 'type' => 'custom', 'scope' => $scope, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()], $extra));
    $e->forceFill(['created_by' => $by->id, 'partner_id' => $extra['partner_id'] ?? null])->save();

    return $e;
}
