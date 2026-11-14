<?php

beforeEach(fn () => $this->travelTo('2026-11-14 03:00:00'));

test('missions timeline places milestones, tasks and today inside the quarter', function () {
    $t = orgTree();
    $this->travelTo('2026-10-12 03:00:00');
    $m = mkMission($t['rep'], $t['sales']->id);
    $ms = $m->milestones()->create(['title' => 'Pricing draft', 'due_date' => '2026-10-19', 'sort_order' => 1]);
    $task = mkTask(['title' => 'Write copy', 'r' => $t['rep']->id, 'due_date' => '2026-10-25']);
    $task->forceFill(['source_type' => 'milestone', 'source_id' => $ms->id])->save();
    $this->travelTo('2026-11-14 03:00:00');

    actAs($t['lead'])->get(ou('missions.index', ['view' => 'timeline']))->assertOk()
        ->assertSee('Pricing draft')->assertSee('Write copy')->assertSee('Milestone, Mon, 19 Oct, late')
        ->assertSee('x="15.93%"', false)
        ->assertSee('x="7.69%" y="18" width="15.39%"', false)
        ->assertSee('x1="44.51%"', false)
        ->assertSee('Task, Rio Rep, due Sun, 25 Oct, overdue');
});

test('missions list shows the quarter tally and the timeline has an empty state', function () {
    $t = orgTree();
    mkMission($t['rep'], $t['sales']->id);
    mkMission($t['rep'], null, 'off_track');
    actAs($t['rep'])->get(ou('missions.index'))->assertSee('2 Missions: 1 on track, 1 off track');
    actAs($t['rep'])->get(ou('missions.index', ['view' => 'timeline', 'qi' => 3]))->assertSee('No Missions this quarter');
});

test('task rows mark done in one click, and ask first when steps are open', function () {
    $t = orgTree();
    $task = mkTask(['title' => 'Call supplier', 'r' => $t['rep']->id]);
    actAs($t['rep'])->get(ou('tasks.index'))->assertSee('Mark done: Call supplier');
    actAs($t['ops'])->get(ou('tasks.index', ['tab' => 'all']))->assertDontSee('Mark done: Call supplier')->assertSee('Call supplier');

    $task->steps()->create(['title' => 'Find number', 'sort_order' => 1]);
    actAs($t['rep'])->from(ou('tasks.index'))->post(ou('tasks.status', [$task]), ['status' => 'done'])->assertSessionHas('confirm_done');
    expect($task->fresh()->status)->toBe('todo');
    actAs($t['rep'])->withSession(['confirm_done' => [$task->id, 1]])->get(ou('tasks.index'))->assertSee("1 step isn't checked. Mark as done anyway?");
    actAs($t['rep'])->post(ou('tasks.status', [$task]), ['status' => 'done', 'confirmed' => 1]);
    expect($task->fresh()->status)->toBe('done');
    actAs($t['ops'])->post(ou('tasks.status', [$task]), ['status' => 'todo'])->assertForbidden();
});

test('the last team picked is remembered across team pages', function () {
    $t = orgTree();
    $key = 'team_'.$t['org']->id;
    actAs($t['admin'])->get(ou('problems.index', ['team' => $t['sales']->id]))->assertCookie($key, (string) $t['sales']->id);
    actAs($t['admin'])->withCookie($key, (string) $t['sales']->id)->get(ou('metrics.index'))->assertViewHas('team', $t['sales']->id);
    actAs($t['admin'])->withCookie($key, '999999')->get(ou('metrics.index'))->assertViewHas('team', $t['root']->id);
});

test('home says how soon the next meeting is', function () {
    $t = orgTree();
    mkEvent($t['admin'], 'team', ['type' => 'weekly_meeting', 'team_seat_id' => $t['root']->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    actAs($t['admin'])->get(ou('home'))->assertSee('Tomorrow');
});
