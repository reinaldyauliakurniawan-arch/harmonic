<?php

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Seat;
use App\Models\User;
use App\Services\Onboarding;
use App\Services\Tenant;

test('self-signup creates a pending organization with the founder as admin in the root seat', function () {
    $this->post('/signup', [
        'name' => 'Founder One', 'email' => 'founder@example.com', 'password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12', 'organization' => 'Tide Works',
    ])->assertRedirect('/home');

    $org = Organization::where('slug', 'tide-works')->firstOrFail();
    expect($org->status)->toBe('pending')->and($org->signup_source)->toBe('self');
    $m = Membership::withoutGlobalScope('organization')->where('organization_id', $org->id)->first();
    expect($m->is_org_admin)->toBeTrue();
    expect(Seat::withoutGlobalScope('organization')->where('organization_id', $org->id)->first()->holder_id)->toBe($m->id);

    $this->get('/home')->assertSee('waiting for approval');
    $this->get('/o/tide-works')->assertStatus(403)->assertSee('waiting for approval');
});

test('signup rejects weak passwords and makes slugs unique', function () {
    $this->post('/signup', ['name' => 'A B', 'email' => 'a@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'organization' => 'X'])
        ->assertSessionHasErrors(['password', 'organization']);
    makeOrg('Tide Works');
    app(Tenant::class)->forget();
    expect(Organization::uniqueSlug('Tide Works'))->toBe('tide-works-2');
});

test('signup is limited to 5 per IP per hour', function () {
    foreach (range(1, 5) as $i) {
        auth()->logout();
        $this->post('/signup', ['name' => 'Person '.$i, 'email' => "p$i@example.com", 'password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12', 'organization' => 'Org '.$i]);
    }
    auth()->logout();
    $this->post('/signup', ['name' => 'Six', 'email' => 'p6@example.com', 'password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12', 'organization' => 'Org 6'])->assertStatus(429);
});

test('a non-member gets 404 for another organization, never 403', function () {
    [$a] = makeOrg('Alpha');
    [$b, $bAdmin] = makeOrg('Beta');
    actAs($bAdmin)->get('/o/'.$a->slug)->assertNotFound();
    actAs($bAdmin)->get('/o/'.$b->slug)->assertOk();
});

test('records of another organization resolve to 404 through route binding', function () {
    [$a, $aAdmin] = makeOrg('Alpha');
    [$inv] = app(Onboarding::class)->invite($a, 'x@example.com', false, null, $aAdmin->id);
    [$b, $bAdmin] = makeOrg('Beta');
    actAs($bAdmin)->delete('/o/'.$b->slug.'/invitations/'.$inv->id)->assertNotFound();
});

test('suspended organizations show the suspension notice on every page', function () {
    [$org, $admin] = makeOrg();
    $org->forceFill(['status' => 'suspended'])->save();
    actAs($admin)->get('/o/'.$org->slug.'/people')->assertStatus(403)->assertSee('This organization is suspended. Contact your administrator.');
});

test('the last active Org Admin cannot be removed as admin or deactivated', function () {
    [$org, $admin] = makeOrg();
    actAs($admin)->post(ou('people.admin', [$admin]))->assertSessionHasErrors(['member' => 'Make someone else an admin first.']);
    actAs($admin)->get(ou('handover.show', ['type' => 'deactivate', 'member' => $admin->id]))->assertSessionHasErrors('member');
    expect($admin->fresh()->is_org_admin)->toBeTrue()->and($admin->fresh()->status)->toBe('active');

    $other = makeMember('Cleo Second', true);
    actAs($admin)->post(ou('people.admin', [$admin]))->assertSessionHasNoErrors();
    expect($admin->fresh()->is_org_admin)->toBeFalse();
});

test('only Org Admins reach people, settings, and activity', function () {
    [$org] = makeOrg();
    $m = makeMember();
    foreach (['people.index', 'settings.edit', 'activity.index'] as $r) {
        actAs($m)->get(ou($r))->assertForbidden()->assertSee("You don't have access to this.");
    }
});

test('invitations: link shown once, accepted by a new person, seat assigned', function () {
    [$org, $admin, $root] = makeOrg();
    $seat = makeSeat('Sales Lead', $root);
    $res = actAs($admin)->post(ou('people.invite'), ['emails' => 'new@example.com', 'seat_id' => $seat->id, 'make_org_admin' => 0]);
    $link = session('links')['new@example.com'];
    $token = basename($link);
    expect(Invitation::first()->token_hash)->toBe(hash('sha256', $token));

    auth()->logout();
    $this->post('/invitations/'.$token, ['name' => 'New Person', 'password' => 'abcdefgh12', 'password_confirmation' => 'abcdefgh12'])
        ->assertRedirect('/o/'.$org->slug);
    $user = User::where('email', 'new@example.com')->first();
    $m = Membership::where('user_id', $user->id)->first();
    expect($m->status)->toBe('active')->and($seat->fresh()->holder_id)->toBe($m->id);
    expect(ActivityLog::where('action', 'member.joined')->where('subject_id', $m->id)->exists())->toBeTrue();
    $this->get('/invitations/'.$token)->assertStatus(410);
});

test('inviting an active member sends nothing; resend voids the old link', function () {
    [$org, $admin] = makeOrg();
    $b = makeMember();
    actAs($admin)->post(ou('people.invite'), ['emails' => $b->user->email])->assertSessionHas('status', 'Already a member.');
    expect(Invitation::count())->toBe(0);

    actAs($admin)->post(ou('people.invite'), ['emails' => 'x@example.com']);
    $old = basename(session('links')['x@example.com']);
    actAs($admin)->post(ou('people.resend', [Invitation::first()]));
    $new = basename(session('links')['x@example.com']);
    $this->get('/invitations/'.$old)->assertStatus(410);
    $this->get('/invitations/'.$new)->assertOk();
});

test('a departed member who accepts again is reactivated and logged as rejoined; a filled seat is skipped', function () {
    [$org, $admin, $root] = makeOrg();
    $b = makeMember();
    $b->forceFill(['status' => 'departed'])->save();
    $seat = makeSeat('Ops', $root, makeMember('Seat Taken'));
    [$inv, $token] = app(Onboarding::class)->invite($org, $b->user->email, false, $seat->id, $admin->id);
    actAs($b)->post('/invitations/'.$token)->assertSessionHas('status', "You've joined. Seat was already filled, so it wasn't assigned.");
    expect($b->fresh()->status)->toBe('active')->and(ActivityLog::where('action', 'member.rejoined')->exists())->toBeTrue();
    expect(Membership::where('user_id', $b->user_id)->count())->toBe(1);
});

test('sign-in, wrong password, and the login rate limit', function () {
    [$org, $admin] = makeOrg();
    $email = $admin->user->email;
    $this->post('/login', ['email' => $email, 'password' => 'wrong-pass1'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $email, 'password' => 'password123'])->assertRedirect('/home');
    $this->get('/home')->assertRedirect('/o/'.$org->slug);
    auth()->logout();
    foreach (range(1, 10) as $i) {
        $this->post('/login', ['email' => $email, 'password' => 'nope'.$i]);
    }
    $this->post('/login', ['email' => $email, 'password' => 'password123'])->assertStatus(429);
});

test('SUPER_ADMIN_EMAIL flags that account on sign-in', function () {
    $u = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password123']);
    config(['app.super_admin_email' => 'owner@example.com']);
    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'password123']);
    expect($u->fresh()->is_super_admin)->toBeTrue();
});

test('super admin approves, suspends, deletes, restores and rejects', function () {
    $sa = User::create(['name' => 'Root', 'email' => 'root@example.com', 'password' => 'password123']);
    $sa->forceFill(['is_super_admin' => true])->save();
    $founder = User::create(['name' => 'F', 'email' => 'f@example.com', 'password' => 'password123']);
    [$org] = app(Onboarding::class)->createOrganization('Pend', 'pending', 'self', $founder);
    $this->actingAs($sa)->post("/admin/organizations/{$org->id}/approve")->assertRedirect();
    expect($org->fresh()->status)->toBe('active');
    $this->post("/admin/organizations/{$org->id}/delete");
    expect($org->fresh()->status)->toBe('deleted')->and($org->fresh()->purge_after->toDateString())->toBe(now()->addDays(30)->toDateString());
    $this->post("/admin/organizations/{$org->id}/restore");
    expect($org->fresh()->status)->toBe('active');

    [$p] = app(Onboarding::class)->createOrganization('Pend Two', 'pending', 'self', User::create(['name' => 'G', 'email' => 'g@example.com', 'password' => 'password123']));
    $this->post("/admin/organizations/{$p->id}/reject");
    expect(Organization::find($p->id))->toBeNull()->and(Membership::withoutGlobalScope('organization')->where('organization_id', $p->id)->count())->toBe(0);

    $this->post('/admin/organizations', ['name' => 'Invited Co', 'email' => 'boss@example.com'])->assertSessionHas('link');
    expect(Organization::where('name', 'Invited Co')->first()->status)->toBe('active');
});

test('non super admins cannot open the console', function () {
    [, $admin] = makeOrg();
    actAs($admin)->get('/admin')->assertForbidden();
});

test('changing the quarter start date asks for confirmation and is logged', function () {
    [$org, $admin] = makeOrg();
    $data = ['name' => 'Acme', 'timezone' => 'Asia/Jakarta', 'week_start_day' => 1, 'quarter_start_date' => '2027-01-04', 'evaluator_bar_max_mixed' => 1, 'evaluator_bar_max_minus' => 0];
    actAs($admin)->put(ou('settings.update'), $data)->assertSessionHas('confirm_quarter');
    expect($org->fresh()->quarter_start_date->toDateString())->toBe('2026-10-05');
    actAs($admin)->put(ou('settings.update'), $data + ['confirmed' => 1]);
    expect($org->fresh()->quarter_start_date->toDateString())->toBe('2027-01-04');
    expect(ActivityLog::where('action', 'org.settings_changed')->exists())->toBeTrue();
});

test('reset link created by an admin lets the member choose a new password', function () {
    [$org, $admin] = makeOrg();
    $b = makeMember();
    actAs($admin)->post(ou('people.reset-link', [$b]));
    $url = session('links')[$b->user->email];
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    auth()->logout();
    $this->post('/reset-password', ['token' => basename(parse_url($url, PHP_URL_PATH)), 'email' => $q['email'], 'password' => 'newpass1234', 'password_confirmation' => 'newpass1234'])->assertRedirect('/login');
    $this->post('/login', ['email' => $b->user->email, 'password' => 'newpass1234'])->assertRedirect('/home');
});

test('lazy scheduler purges organizations past their purge date', function () {
    [$org, $admin] = makeOrg('Gone');
    $org->forceFill(['status' => 'deleted', 'purge_after' => now()->subDay()->toDateString()])->save();
    [$live, $liveAdmin] = makeOrg('Live');
    actAs($liveAdmin)->get('/o/'.$live->slug)->assertOk();
    expect(Organization::find($org->id))->toBeNull();
});

test('security headers are set', function () {
    $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
