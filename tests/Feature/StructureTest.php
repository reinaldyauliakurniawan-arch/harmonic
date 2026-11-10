<?php

use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Metric;
use App\Models\Seat;
use App\Models\Task;
use App\Services\Formation;
use App\Services\Recurrence;
use App\Services\Tasks;
use Carbon\CarbonImmutable;

test('teams are derived from the seat tree and change on add, move, delete', function () {
    $t = orgTree();
    $f = Formation::load();
    expect(collect($f->teams())->pluck('id')->all())->toBe([$t['root']->id, $t['sales']->id]);
    expect($f->teamMemberIds($t['sales']->id))->toEqualCanonicalizing([$t['lead']->id, $t['rep']->id]);
    expect($f->teamIdsOf($t['lead']->id))->toEqualCanonicalizing([$t['root']->id, $t['sales']->id]);
    expect($f->isLeadership($t['lead']->id))->toBeTrue()->and($f->isLeadership($t['rep']->id))->toBeFalse();

    makeSeat('Ops Analyst', $t['opsSeat']);
    expect(Formation::load()->isTeam($t['opsSeat']->id))->toBeTrue();

    $moved = Formation::load()->with([$t['repSeat']->id => ['parent_id' => $t['opsSeat']->id]]);
    expect($moved->isTeam($t['sales']->id))->toBeFalse()->and($moved->teamMemberIds($t['opsSeat']->id))->toContain($t['rep']->id);

    $deleted = Formation::load()->with([$t['repSeat']->id => ['__deleted' => true]]);
    expect($deleted->isTeam($t['sales']->id))->toBeFalse();
});

test('leadership team includes ancestors of the flagged seat', function () {
    $t = orgTree();
    $t['root']->forceFill(['is_leadership_team' => false])->save();
    $t['sales']->forceFill(['is_leadership_team' => true])->save();
    $f = Formation::load();
    expect($f->leadershipSeatIds())->toEqualCanonicalizing([$t['sales']->id, $t['repSeat']->id, $t['root']->id]);
    expect($f->isLeadership($t['ops']->id))->toBeFalse();
});

test('handover on deactivation reassigns every open item type and cleans up C/I and 360 slots', function () {
    $t = orgTree();
    $rep = $t['rep'];
    $task = mkTask(['r' => $rep->id, 'a' => $rep->id, 'by' => $t['lead']->id]);
    $taskR = mkTask(['r' => $rep->id, 'a' => $t['ops']->id]);
    $doneTask = mkTask(['r' => $rep->id, 'status' => 'done']);
    $ciTask = mkTask(['r' => $t['ops']->id]);
    DB::table('task_people')->insert(['task_id' => $ciTask->id, 'member_id' => $rep->id, 'role' => 'consulted']);
    $mission = mkMission($rep);
    $prob = mkProblem($rep, $t['sales']->id);
    $ev = mkEvent($rep);
    $private = mkEvent($rep, 'private');

    actAs($t['admin'])->get(ou('handover.show', ['type' => 'deactivate', 'member' => $rep->id]))->assertOk()
        ->assertSee('Hand everything to')->assertSee('Tasks (2)');
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => ['type' => 'deactivate', 'member' => $rep->id]])->assertRedirect();

    // Default receiver: nearest filled seat above (D13) = Sales Lead.
    expect($task->fresh()->responsible_id)->toBe($t['lead']->id)->and($task->fresh()->accountable_id)->toBe($t['lead']->id);
    expect($taskR->fresh()->responsible_id)->toBe($t['lead']->id)->and($taskR->fresh()->accountable_id)->toBe($t['ops']->id);
    expect($doneTask->fresh()->responsible_id)->toBe($rep->id);
    expect(DB::table('task_people')->where('member_id', $rep->id)->count())->toBe(0);
    expect($mission->fresh()->owner_id)->toBe($t['lead']->id)->and($prob->fresh()->owner_id)->toBe($t['lead']->id);
    expect($ev->fresh()->created_by)->toBe($t['lead']->id)->and($private->fresh()->created_by)->toBe($rep->id);
    expect($rep->fresh()->status)->toBe('departed')->and($t['repSeat']->fresh()->holder_id)->toBeNull();
    $log = ActivityLog::where('action', 'handover.completed')->first();
    expect($log->data['counts'])->toEqualCanonicalizing(['tasks' => 2, 'missions' => 1, 'problems' => 1, 'events' => 1]);
});

test('handover lets each item go to a different receiver and rejects the leaving person', function () {
    $t = orgTree();
    $a = mkTask(['r' => $t['rep']->id]);
    $b = mkTask(['r' => $t['rep']->id]);
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => ['type' => 'deactivate', 'member' => $t['rep']->id],
        'receivers' => [$t['rep']->id => ['all' => $t['ops']->id, 'items' => ['tasks:'.$b->id => $t['admin']->id]]]]);
    expect($a->fresh()->responsible_id)->toBe($t['ops']->id)->and($b->fresh()->responsible_id)->toBe($t['admin']->id);

    $c = mkTask(['r' => $t['lead']->id]);
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => ['type' => 'deactivate', 'member' => $t['lead']->id],
        'receivers' => [$t['lead']->id => ['all' => $t['lead']->id]]])->assertSessionHasErrors('receiver');
    expect($t['lead']->fresh()->status)->toBe('active')->and($c->fresh()->responsible_id)->toBe($t['lead']->id);
});

test('replacing a seat holder hands team items to the new holder by default', function () {
    $t = orgTree();
    $new = makeMember('Nina New');
    $teamTask = mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id]);
    $otherTask = mkTask(['r' => $t['rep']->id]);
    actAs($t['admin'])->post(ou('formation.holder', [$t['repSeat']]), ['holder_id' => $new->id])->assertRedirectContains('handover');
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => ['type' => 'vacate', 'seat' => $t['repSeat']->id, 'holder' => $new->id]]);
    expect($t['repSeat']->fresh()->holder_id)->toBe($new->id);
    expect($teamTask->fresh()->responsible_id)->toBe($new->id)->and($otherTask->fresh()->responsible_id)->toBe($t['rep']->id);
});

test('vacating a seat with nothing to hand over says so', function () {
    $t = orgTree();
    actAs($t['admin'])->get(ou('handover.show', ['type' => 'vacate', 'seat' => $t['opsSeat']->id]))->assertSee('Nothing to hand over.');
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => ['type' => 'vacate', 'seat' => $t['opsSeat']->id]]);
    expect($t['opsSeat']->fresh()->holder_id)->toBeNull();
});

test('moving a seat dissolves an emptied team and moves its items to the chosen destination', function () {
    $t = orgTree();
    $metric = new Metric(['name' => 'Leads', 'team_seat_id' => $t['sales']->id, 'owner_seat_id' => $t['repSeat']->id, 'unit' => 'number', 'operator' => 'gte', 'goal_value' => 10]);
    $metric->save();
    $prob = mkProblem($t['lead'], $t['sales']->id);
    $teamTask = mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id]);
    $trigger = ['type' => 'move', 'seat' => $t['repSeat']->id, 'parent' => $t['opsSeat']->id];

    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => $trigger])->assertSessionHasErrors('destination');
    expect($t['repSeat']->fresh()->parent_id)->toBe($t['sales']->id);

    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => $trigger + ['destination' => $t['root']->id]])->assertSessionHasNoErrors();
    expect($t['repSeat']->fresh()->parent_id)->toBe($t['opsSeat']->id);
    expect($metric->fresh()->team_seat_id)->toBe($t['root']->id)->and($prob->fresh()->team_seat_id)->toBe($t['root']->id);
    // Rep left the Sales team, so the Sales task went through Handover to the nearest holder above (D13).
    expect($teamTask->fresh()->responsible_id)->toBe($t['lead']->id);
    expect(ActivityLog::where('action', 'seat.moved')->exists())->toBeTrue();
});

test('a seat cannot move under its own child', function () {
    $t = orgTree();
    actAs($t['admin'])->get(ou('handover.show', ['type' => 'move', 'seat' => $t['sales']->id, 'parent' => $t['repSeat']->id]))
        ->assertSessionHasErrors(['parent' => "A seat can't move under its own child."]);
});

test('deleting a seat: blocked with children, needs a replacement owner for seat-owned items', function () {
    $t = orgTree();
    actAs($t['admin'])->get(ou('handover.show', ['type' => 'delete_seat', 'seat' => $t['sales']->id]))
        ->assertSessionHasErrors(['seat' => 'Move or delete its child seats first.']);

    $metric = new Metric(['name' => 'Calls', 'team_seat_id' => $t['root']->id, 'owner_seat_id' => $t['opsSeat']->id, 'unit' => 'number', 'operator' => 'gte', 'goal_value' => 1]);
    $metric->save();
    $trigger = ['type' => 'delete_seat', 'seat' => $t['opsSeat']->id];
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => $trigger])->assertSessionHasErrors('replacement');
    actAs($t['admin'])->post(ou('handover.store'), ['trigger' => $trigger + ['replacement' => $t['root']->id]])->assertSessionHasNoErrors();
    expect(Seat::find($t['opsSeat']->id))->toBeNull()->and($metric->fresh()->owner_seat_id)->toBe($t['root']->id);
});

test('deleting an account hands over work and anonymizes the user after the last organization', function () {
    $t = orgTree();
    $task = mkTask(['r' => $t['rep']->id]);
    $user = $t['rep']->user;
    actAs($t['rep'])->post(ou('handover.store'), ['trigger' => ['type' => 'account', 'member' => $t['rep']->id]])->assertRedirect('/profile');
    expect($task->fresh()->responsible_id)->toBe($t['lead']->id);
    expect($user->fresh()->name)->toBe('Former member')->and($user->fresh()->email)->toBe('deleted+'.$user->id.'@invalid.local');
    expect($user->fresh()->anonymized_at)->not->toBeNull();
});

test('only Org Admins can run seat and member handovers', function () {
    $t = orgTree();
    actAs($t['lead'])->get(ou('handover.show', ['type' => 'deactivate', 'member' => $t['rep']->id]))->assertForbidden();
    actAs($t['lead'])->post(ou('handover.store'), ['trigger' => ['type' => 'account', 'member' => $t['rep']->id]])->assertForbidden();
});

test('board column sets status and status changes move the card', function () {
    $t = orgTree();
    actAs($t['rep'])->post(ou('boards.store'), ['title' => 'Launch']);
    $board = Board::first();
    $cols = $board->columns()->get();
    expect($cols->pluck('title')->all())->toBe(['To do', 'Doing', 'Done'])->and($cols->where('is_done_column', true)->count())->toBe(1);

    actAs($t['rep'])->post(ou('tasks.store'), ['full' => 1, 'board_id' => $board->id, 'title' => 'Write copy', 'output' => 'Copy doc', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-12-01']);
    $task = Task::first();
    expect($task->board_column_id)->toBe($cols[0]->id)->and($task->status)->toBe('todo');

    actAs($t['rep'])->postJson(ou('boards.move', [$board, $task]), ['column_id' => $cols[1]->id])->assertJson(['status' => 'doing']);
    actAs($t['rep'])->post(ou('boards.move', [$board, $task]), ['column_id' => $cols[2]->id]);
    expect($task->fresh()->status)->toBe('done')->and($task->fresh()->completed_at)->not->toBeNull();

    actAs($t['rep'])->post(ou('tasks.status', [$task]), ['status' => 'todo']);
    expect($task->fresh()->board_column_id)->toBe($cols[0]->id)->and($task->fresh()->completed_at)->toBeNull();

    actAs($t['rep'])->post(ou('boards.columns.store', [$board]), ['title' => 'Review']);
    $review = $board->columns()->where('title', 'Review')->first();
    expect($board->columns()->get()->last()->is_done_column)->toBeTrue();
    actAs($t['rep'])->delete(ou('boards.columns.destroy', [$board, $cols[0]]))->assertSessionHasErrors('column');
    actAs($t['rep'])->delete(ou('boards.columns.destroy', [$board, $review]))->assertSessionHasNoErrors();
});

test('marking done with unchecked steps asks first; overlap of C/I with R/A is rejected', function () {
    $t = orgTree();
    actAs($t['rep'])->post(ou('tasks.store'), ['full' => 1, 'title' => 'Plan', 'output' => 'Plan doc', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-12-01', 'steps_text' => "One\nTwo"]);
    $task = Task::first();
    expect($task->steps()->count())->toBe(2);
    actAs($t['rep'])->post(ou('tasks.status', [$task]), ['status' => 'done'])->assertSessionHas('confirm_done', [$task->id, 2]);
    expect($task->fresh()->status)->toBe('todo');
    actAs($t['rep'])->post(ou('tasks.status', [$task]), ['status' => 'done', 'confirmed' => 1]);
    expect($task->fresh()->status)->toBe('done');

    actAs($t['rep'])->put(ou('tasks.update', [$task]), ['title' => 'Plan', 'output' => 'Plan doc', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-12-01', 'consulted' => [$t['rep']->id]])
        ->assertSessionHasErrors('consulted');
});

test('task permissions follow R, A, creator, and team leader', function () {
    $t = orgTree();
    $task = mkTask(['r' => $t['rep']->id, 'by' => $t['rep']->id, 'team_seat_id' => $t['sales']->id]);
    actAs($t['ops'])->post(ou('tasks.status', [$task]), ['status' => 'doing'])->assertForbidden();
    actAs($t['ops'])->delete(ou('tasks.destroy', [$task]))->assertForbidden();
    actAs($t['lead'])->post(ou('tasks.status', [$task]), ['status' => 'doing'])->assertRedirect();
    actAs($t['ops'])->get(ou('tasks.show', [$task]))->assertOk();
});

test('completion rate counts tasks done on or before their due date', function () {
    $t = orgTree();
    $week = CarbonImmutable::parse('2026-11-02');
    mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id, 'due_date' => '2026-11-03', 'status' => 'done'])->forceFill(['completed_at' => '2026-11-03 02:00:00'])->save();
    mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id, 'due_date' => '2026-11-04', 'status' => 'done'])->forceFill(['completed_at' => '2026-11-06 02:00:00'])->save();
    mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id, 'due_date' => '2026-11-05']);
    mkTask(['r' => $t['rep']->id, 'team_seat_id' => $t['sales']->id, 'due_date' => '2026-11-05', 'status' => 'cancelled']);
    expect(app(Tasks::class)->completionRate($t['sales']->id, $week))->toBe(33.3);
});

test('weekly recurrence stores occurrences and "this and following" splits the series', function () {
    $t = orgTree();
    actAs($t['admin'])->post(ou('calendar.store'), ['type' => 'custom', 'title' => 'Standup', 'scope' => 'org', 'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:15', 'repeat' => 'weekly'])->assertRedirect();
    $events = Event::orderBy('starts_at')->get();
    expect($events->count())->toBeGreaterThanOrEqual(26)->and($events->pluck('series_id')->unique()->count())->toBe(1);

    $fifth = $events[4];
    actAs($t['admin'])->put(ou('calendar.update', [$fifth]), ['title' => 'Standup v2', 'date' => $fifth->starts_at->setTimezone('Asia/Jakarta')->toDateString(), 'start_time' => '10:00', 'end_time' => '10:15', 'apply' => 'following']);
    $after = Event::orderBy('starts_at')->get();
    expect($after[3]->title)->toBe('Standup')->and($after[4]->title)->toBe('Standup v2')->and($after->last()->title)->toBe('Standup v2');
    expect($after[3]->series_id)->not->toBe($after[4]->series_id);
    expect(EventSeries::find($after[3]->series_id)->until)->not->toBeNull();
    expect($after[4]->starts_at->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('10:00');

    actAs($t['admin'])->delete(ou('calendar.destroy', [$after[10]]), ['apply' => 'following']);
    expect(Event::where('series_id', $after[4]->series_id)->count())->toBe(6);
});

test('monthly recurrence clamps to month end and quarterly keeps the week of the quarter', function () {
    $t = orgTree();
    $r = app(Recurrence::class);
    $first = CarbonImmutable::parse('2027-01-31 09:00', 'Asia/Jakarta')->utc();
    expect($r->nth('monthly', $first, 1)->setTimezone('Asia/Jakarta')->toDateString())->toBe('2027-02-28');
    expect($r->nth('monthly', $first, 2)->setTimezone('Asia/Jakarta')->toDateString())->toBe('2027-03-31');
    // Quarter starts 2026-10-05; an event in week 3 (Wed 21 Oct) recurs in week 3 of next quarter.
    $q = CarbonImmutable::parse('2026-10-21 09:00', 'Asia/Jakarta')->utc();
    expect($r->nth('quarterly', $q, 1)->setTimezone('Asia/Jakarta')->toDateString())->toBe('2027-01-20');
});

test('private events and their notes are invisible to everyone else, including Org Admins', function () {
    $t = orgTree();
    $private = mkEvent($t['rep'], 'private', ['type' => 'guide_reflection', 'notes' => 'my secret']);
    $pair = mkEvent($t['lead'], 'pair', ['type' => 'guide_checkin', 'partner_id' => $t['rep']->id]);
    expect(Event::visibleTo($t['admin'])->pluck('id')->all())->not->toContain($private->id)->not->toContain($pair->id);
    expect(Event::visibleTo($t['rep'])->pluck('id')->all())->toContain($private->id)->toContain($pair->id);

    actAs($t['admin'])->get(ou('calendar.index', ['event' => $private->id]))->assertForbidden();
    actAs($t['admin'])->get(ou('calendar.index', ['date' => $private->starts_at->toDateString()]))->assertDontSee('my secret');
    actAs($t['rep'])->get(ou('calendar.index', ['event' => $private->id, 'date' => $private->starts_at->toDateString()]))->assertSee('my secret');
    actAs($t['rep'])->patchJson(ou('calendar.notes', [$pair]), ['notes' => 'both of us'])->assertOk();
    actAs($t['ops'])->patchJson(ou('calendar.notes', [$pair]), ['notes' => 'x'])->assertForbidden();
});

test('event creation rights follow the event type', function () {
    $t = orgTree();
    $d = ['date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00'];
    actAs($t['rep'])->post(ou('calendar.store'), $d + ['type' => 'custom', 'scope' => 'org'])->assertForbidden();
    actAs($t['rep'])->post(ou('calendar.store'), $d + ['type' => 'guide_reflection'])->assertRedirect();
    actAs($t['rep'])->post(ou('calendar.store'), $d + ['type' => 'quarterly_session', 'team_seat_id' => $t['sales']->id])->assertForbidden();
    actAs($t['lead'])->post(ou('calendar.store'), $d + ['type' => 'quarterly_session', 'team_seat_id' => $t['sales']->id])->assertRedirect();
    actAs($t['lead'])->post(ou('calendar.store'), $d + ['type' => 'guide_checkin', 'partner_id' => $t['rep']->id])->assertRedirect();
    actAs($t['ops'])->post(ou('calendar.store'), $d + ['type' => 'guide_checkin', 'partner_id' => $t['rep']->id])->assertForbidden();
});

test('phase 1 pages render for a member', function () {
    $t = orgTree();
    $task = mkTask(['r' => $t['rep']->id]);
    actAs($t['rep'])->get(ou('formation.index'))->assertOk()->assertSee('Sales Lead')->assertSee('Leadership');
    actAs($t['rep'])->get(ou('tasks.index'))->assertOk()->assertSee($task->title);
    actAs($t['rep'])->get(ou('tasks.show', [$task]))->assertOk();
    actAs($t['rep'])->get(ou('boards.index'))->assertOk()->assertSee('Start a board');
    actAs($t['rep'])->get(ou('calendar.index', ['view' => 'week']))->assertOk();
    actAs($t['rep'])->get(ou('calendar.index', ['view' => 'agenda', 'new' => 1, 'due' => 1]))->assertOk()->assertSee('New event');
    actAs($t['admin'])->get(ou('formation.create'))->assertOk();
    actAs($t['rep'])->get(ou('formation.create'))->assertForbidden();
});

test('seats under a parent can be reordered, and only with the full set of children', function () {
    $t = orgTree();
    actAs($t['admin'])->postJson(ou('formation.order', [$t['root']]), ['ids' => [$t['opsSeat']->id, $t['sales']->id]])->assertOk();
    expect(Formation::load()->childIds($t['root']->id))->toBe([$t['opsSeat']->id, $t['sales']->id]);
    actAs($t['admin'])->postJson(ou('formation.order', [$t['root']]), ['ids' => [$t['repSeat']->id]])->assertStatus(422);
    actAs($t['lead'])->postJson(ou('formation.order', [$t['root']]), ['ids' => [$t['sales']->id, $t['opsSeat']->id]])->assertForbidden();
});

test('role change and departure sign out existing sessions', function () {
    $t = orgTree();
    actAs($t['rep'])->get(ou('home'))->assertOk();
    $this->travel(5)->seconds();
    // An admin elsewhere makes Rep an Org Admin: Rep's existing session ends.
    $this->actingAs($t['admin']->user);
    session()->forget('password_hash_web');
    session()->put('auth_at', now()->addMinute()->timestamp);
    $this->post(ou('people.admin', [$t['rep']]))->assertRedirect();
    expect($t['rep']->user->fresh()->sessions_valid_from)->not->toBeNull();
    $this->actingAs($t['rep']->user->fresh())->withSession(['auth_at' => now()->subSecond()->timestamp])->get(ou('home'))->assertRedirect('/login');
    $this->actingAs($t['rep']->user->fresh())->withSession(['auth_at' => now()->addMinute()->timestamp])->get(ou('home'))->assertOk();
});

test('a board only offers card moves to people allowed to make them', function () {
    $t = orgTree();
    actAs($t['rep'])->post(ou('boards.store'), ['title' => 'Launch']);
    $board = Board::first();
    actAs($t['rep'])->post(ou('tasks.store'), ['full' => 1, 'board_id' => $board->id, 'title' => 'Write copy', 'output' => 'Copy doc', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-12-01']);

    actAs($t['rep'])->get(ou('boards.show', [$board]))->assertSee('Move Write copy to another column')->assertSee('draggable="true"', false);
    actAs($t['ops'])->get(ou('boards.show', [$board]))->assertOk()->assertDontSee('Move Write copy to another column')->assertDontSee('draggable="true"', false);
});

test('the add seat form lists every seat under Reports to', function () {
    $t = orgTree();
    actAs($t['admin'])->get(ou('formation.create'))
        ->assertSee('<option value="'.$t['sales']->id.'"', false)
        ->assertSee('<option value="'.$t['repSeat']->id.'"', false)
        ->assertDontSee('mapWithKeys');
});

test('repeated field names get their own ids so each label points at its input', function () {
    $t = orgTree();
    $html = actAs($t['admin'])->get(route('profile.edit'))->assertOk()->getContent();
    expect(substr_count($html, 'id="f-current-password"'))->toBe(1)
        ->and(substr_count($html, 'id="f-current-password-2"'))->toBe(1)
        ->and(substr_count($html, 'for="f-current-password-2"'))->toBe(1);
});

test('sidebar groups start closed, remember the ones opened, keep the current group open, and ignore unknown values', function () {
    $t = orgTree();
    $html = actAs($t['admin'])->withUnencryptedCookies(['nav_rail' => '1'])->get(ou('home'))->assertOk()->getContent();
    expect($html)->toContain('nav-rail');

    $html = actAs($t['admin'])->withUnencryptedCookies(['nav_rail' => '0', 'nav_open' => ''])->get(ou('home'))->assertOk()->getContent();
    expect($html)->not->toMatch('/data-nav-group="[^"]+"[^>]*\sopen\s*>/');

    $html = actAs($t['admin'])->withUnencryptedCookies(['nav_rail' => '0', 'nav_open' => 'Direction,<script>'])->get(ou('formation.index'))->assertOk()->getContent();
    expect($html)->toMatch('/data-nav-group="Direction"[^>]*\sopen\s*>/')
        ->and($html)->toMatch('/data-nav-group="Team"[^>]*\sopen\s*>/')
        ->and($html)->toMatch('/data-nav-group="Measurements"[^>]*@toggle="group"\s*>/')
        ->and($html)->not->toContain('<script>');
});
