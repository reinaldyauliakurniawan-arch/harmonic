<?php

use App\Models\Event;
use App\Models\Meeting;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Models\Mission;
use App\Models\Problem;
use App\Services\Access;
use App\Services\Meetings;
use App\Services\Metrics;
use App\Services\Problems;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->travelTo(now()->parse('2026-11-11 03:00:00')));

function mkMetric(array $t, array $attrs = []): Metric
{
    $m = new Metric(array_merge(['name' => 'Qualified leads', 'team_seat_id' => $t['sales']->id, 'owner_seat_id' => $t['repSeat']->id,
        'unit' => 'number', 'decimals' => 0, 'operator' => 'gte', 'goal_value' => 40, 'quarter_rollup' => 'sum', 'auto_problem_after' => 2], $attrs));
    $m->save();

    return $m;
}

test('every operator, rounding, and between edges', function () {
    expect(Metrics::evaluate(40, 'gte', 40, null, 0))->toBe('on')->and(Metrics::evaluate(39, 'gte', 40, null, 0))->toBe('off');
    expect(Metrics::evaluate(5, 'lte', 5, null, 0))->toBe('on')->and(Metrics::evaluate(6, 'lte', 5, null, 0))->toBe('off');
    expect(Metrics::evaluate(49.995, 'eq', 50, null, 2))->toBe('on')->and(Metrics::evaluate(49.994, 'eq', 50, null, 2))->toBe('off');
    expect(Metrics::evaluate(0.4, 'gt', 0, null, 0))->toBe('off')->and(Metrics::evaluate(1, 'gt', 0, null, 0))->toBe('on');
    expect(Metrics::evaluate(3, 'lt', 3, null, 0))->toBe('off')->and(Metrics::evaluate(2.6, 'lt', 3, null, 1))->toBe('on');
    expect(Metrics::evaluate(20, 'between', 20, 30, 0))->toBe('on')->and(Metrics::evaluate(30, 'between', 20, 30, 0))->toBe('on');
    expect(Metrics::evaluate(19.99, 'between', 20, 30, 2))->toBe('off')->and(Metrics::evaluate(30.4, 'between', 20, 30, 0))->toBe('on');
    expect(Metrics::parse('1,500'))->toBe(1500.0)->and(Metrics::parse('25%'))->toBe(25.0)->and(Metrics::parse('-2.5'))->toBe(-2.5)->and(Metrics::parse(''))->toBeNull();
    expect(fn () => Metrics::parse('1.2.3'))->toThrow(ValidationException::class);
    expect(Metrics::format(1500000, 'currency', 0, 'IDR'))->toBe('IDR 1,500,000')->and(Metrics::format(25, 'percent', 0))->toBe('25%')->and(Metrics::format(2.5, 'hours', 1))->toBe('2.5 h');
});

test('saving snapshots the goal; changing the goal leaves past weeks unless reapplied', function () {
    $t = orgTree();
    $m = mkMetric($t);
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => '41'])->assertOk()->assertJson(['status' => 'on']);
    $m->forceFill(['goal_value' => 50])->save();
    $row = MetricValue::first();
    expect($row->goal_value)->toBe(40.0)->and($row->status)->toBe('on');

    actAs($t['lead'])->put(ou('metrics.update', [$m]), ['name' => $m->name, 'owner_seat_id' => $t['repSeat']->id, 'unit' => 'number', 'decimals' => 0,
        'operator' => 'gte', 'goal_value' => 50, 'quarter_rollup' => 'sum', 'auto_problem_after' => 2, 'reapply' => 1])->assertSessionHasNoErrors();
    expect($row->fresh()->goal_value)->toBe(50.0)->and($row->fresh()->status)->toBe('off');
});

test('missing weeks, future weeks, clearing a cell, and who may enter', function () {
    $t = orgTree();
    $m = mkMetric($t);
    $svc = app(Metrics::class);
    // Weeks before the metric existed are not missing; ended weeks after it are.
    expect($svc->cellStatus(null, now()->parse('2026-11-02'), $m))->toBeNull();
    $m->forceFill(['created_at' => '2026-10-01 00:00:00'])->save();
    expect($svc->cellStatus(null, now()->parse('2026-11-02'), $m))->toBe('missing')->and($svc->cellStatus(null, now()->parse('2026-11-09'), $m))->toBeNull();
    expect(MetricValue::count())->toBe(0);

    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-16', 'value' => '1'])->assertForbidden();
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-03', 'value' => '1'])->assertStatus(422);
    actAs($t['ops'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => '1'])->assertForbidden();
    // Owner: current and previous quarter only; the leader any week.
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-04-06', 'value' => '1'])->assertForbidden();
    actAs($t['lead'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-04-06', 'value' => '1'])->assertOk();

    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => '45'])->assertOk();
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => ''])->assertOk()->assertJson(['status' => 'missing']);
    expect(MetricValue::where('week_start', '2026-11-02')->exists())->toBeFalse();
});

test('auto-problem after N consecutive off weeks, only once while open', function () {
    $t = orgTree();
    $m = mkMetric($t);
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-10-26', 'value' => '10']);
    expect(Problem::count())->toBe(0);
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => '12']);
    $p = Problem::firstOrFail();
    expect($p->title)->toBe('Qualified leads off-track for 2 weeks')->and($p->owner_id)->toBe($t['rep']->id)
        ->and($p->source_type)->toBe('metric')->and($p->horizon)->toBe('short')->and($p->team_seat_id)->toBe($t['sales']->id);
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-09', 'value' => '1']);
    expect(Problem::count())->toBe(1);
});

test('only the team leader defines metrics; owner seat must be in the team', function () {
    $t = orgTree();
    $d = ['team_seat_id' => $t['sales']->id, 'name' => 'Calls', 'owner_seat_id' => $t['repSeat']->id, 'unit' => 'number', 'decimals' => 0, 'operator' => 'between', 'goal_value' => 10, 'goal_value_max' => 20, 'quarter_rollup' => 'average'];
    actAs($t['rep'])->post(ou('metrics.store'), $d)->assertForbidden();
    actAs($t['lead'])->post(ou('metrics.store'), ['owner_seat_id' => $t['opsSeat']->id] + $d)->assertSessionHasErrors('owner_seat_id');
    actAs($t['lead'])->post(ou('metrics.store'), ['goal_value_max' => 5] + $d)->assertSessionHasErrors('goal_value_max');
    actAs($t['lead'])->post(ou('metrics.store'), $d)->assertRedirect();
    expect(Metric::where('name', 'Calls')->first()->goal_value_max)->toBe(20.0);
});

test('My Numbers saves every metric in one request', function () {
    $t = orgTree();
    $a = mkMetric($t);
    $b = mkMetric($t, ['name' => 'Demos', 'operator' => 'lte', 'goal_value' => 3]);
    actAs($t['rep'])->get(ou('numbers.index'))->assertOk()->assertSee('Qualified leads')->assertSee('Demos');
    actAs($t['rep'])->post(ou('numbers.store'), ['values' => [$a->id => '50', $b->id => '1.2.3']])->assertSessionHasErrors('values.'.$b->id);
    expect(MetricValue::count())->toBe(0);
    actAs($t['rep'])->post(ou('numbers.store'), ['values' => [$a->id => '50', $b->id => '2']])->assertSessionHasNoErrors();
    expect(MetricValue::where('week_start', '2026-11-09')->count())->toBe(2);
});

test('problem ranks are unique per team and horizon, and a fourth pushes rank 3 out', function () {
    $t = orgTree();
    $ps = collect(range(1, 4))->map(fn () => mkProblem($t['lead'], $t['sales']->id));
    foreach ([1, 2, 3] as $i => $r) {
        actAs($t['rep'])->post(ou('problems.maintain', [$ps[$i]]), ['action' => 'rank', 'rank' => $r]);
    }
    actAs($t['rep'])->post(ou('problems.maintain', [$ps[3]]), ['action' => 'rank', 'rank' => 1]);
    expect($ps->map(fn ($p) => $p->fresh()->rank)->all())->toBe([2, 3, null, 1]);
    actAs($t['ops'])->post(ou('problems.maintain', [$ps[0]]), ['action' => 'rank', 'rank' => 1])->assertForbidden();
});

test('dropping needs a reason; duplicates link back', function () {
    $t = orgTree();
    [$a, $b] = [mkProblem($t['lead'], $t['sales']->id), mkProblem($t['lead'], $t['sales']->id)];
    actAs($t['rep'])->post(ou('problems.maintain', [$a]), ['action' => 'drop'])->assertSessionHasErrors('reason');
    actAs($t['rep'])->post(ou('problems.maintain', [$a]), ['action' => 'duplicate', 'duplicate_of' => $b->id]);
    expect($a->fresh()->status)->toBe('dropped')->and($a->fresh()->duplicate_of_id)->toBe($b->id)->and($a->fresh()->drop_reason)->toBe('Duplicate of Problem x');
    actAs($t['ops'])->post(ou('problems.store'), ['team_seat_id' => $t['sales']->id, 'title' => 'Anyone can raise', 'horizon' => 'long'])->assertRedirect();
});

test('quick solve, upgrade to full, full mode needs a strict task, then evaluate and raise again', function () {
    $t = orgTree();
    $p = mkProblem($t['lead'], $t['sales']->id);
    actAs($t['ops'])->post(ou('solve.start', [$p]), ['mode' => 'quick'])->assertForbidden();
    actAs($t['rep'])->post(ou('solve.start', [$p]), ['mode' => 'quick']);
    expect($p->fresh()->status)->toBe('solving');
    actAs($t['rep'])->post(ou('solve.upgrade', [$p]));
    actAs($t['rep'])->post(ou('solve.start', [$p]), ['mode' => 'quick'])->assertSessionHasErrors('mode');

    actAs($t['rep'])->post(ou('solve.step', [$p, 2]), ['statement' => 'short'])->assertSessionHasErrors('statement');
    actAs($t['rep'])->post(ou('solve.step', [$p, 2]), ['statement' => 'Leads come in without budgets.']);
    actAs($t['rep'])->post(ou('solve.step', [$p, 3]), ['observe' => 'The form has no budget field.']);
    actAs($t['rep'])->post(ou('solve.step', [$p, 5]), ['solution' => 'Add a budget field.'])->assertSessionHasErrors('solution');

    $task = ['full' => 1, 'strict' => 1, 'source_type' => 'problem', 'source_id' => $p->id, 'title' => 'Add field', 'output' => 'Form with budget', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-11-20'];
    actAs($t['rep'])->post(ou('tasks.store'), $task)->assertSessionHasErrors(['accountable_id', 'definition_of_done']);
    actAs($t['rep'])->post(ou('tasks.store'), $task + ['accountable_id' => $t['lead']->id, 'definition_of_done' => 'Live on site', 'steps_text' => "Design\nShip"])->assertSessionHasNoErrors();
    actAs($t['rep'])->post(ou('solve.step', [$p, 5]), ['solution' => 'Add a budget field.']);
    expect($p->fresh()->status)->toBe('solved')->and($p->fresh()->solve->evaluate_on->toDateString())->toBe('2026-11-25');

    actAs($t['rep'])->post(ou('solve.evaluate', [$p]), ['evaluation_result' => 'failed', 'evaluation' => 'Still no budgets.']);
    actAs($t['rep'])->post(ou('solve.again', [$p]))->assertRedirect();
    expect(Problem::where('source_type', 'problem')->where('source_id', $p->id)->where('status', 'open')->exists())->toBeTrue();
});

test('missions: who creates each level, status, close and carry', function () {
    $t = orgTree();
    $d = ['title' => 'Launch the new site', 'done_definition' => 'Live with analytics running', 'quarter_start' => '2026-10-05'];
    actAs($t['rep'])->post(ou('missions.store'), $d + ['level' => 'company', 'owner_id' => $t['rep']->id])->assertForbidden();
    actAs($t['rep'])->post(ou('missions.store'), $d + ['level' => 'team', 'team_seat_id' => $t['sales']->id, 'owner_id' => $t['rep']->id])->assertForbidden();
    actAs($t['rep'])->post(ou('missions.store'), $d + ['level' => 'personal', 'owner_id' => $t['ops']->id])->assertForbidden();
    actAs($t['lead'])->post(ou('missions.store'), $d + ['level' => 'personal', 'owner_id' => $t['rep']->id])->assertRedirect();
    actAs($t['lead'])->post(ou('missions.store'), $d + ['level' => 'team', 'team_seat_id' => $t['sales']->id, 'owner_id' => $t['ops']->id])->assertSessionHasErrors('owner_id');
    actAs($t['admin'])->post(ou('missions.store'), $d + ['level' => 'company', 'owner_id' => $t['admin']->id])->assertRedirect();

    $m = Mission::where('level', 'personal')->first();
    actAs($t['lead'])->post(ou('missions.status', [$m]), ['status' => 'off_track'])->assertForbidden();
    actAs($t['rep'])->post(ou('missions.status', [$m]), ['status' => 'off_track']);
    expect($m->fresh()->status)->toBe('off_track');

    $this->travelTo(now()->parse('2027-01-06 03:00:00'));
    actAs($t['rep'])->post(ou('missions.close', [$m]), ['result' => 'carry']);
    expect($m->fresh()->status)->toBe('not_done');
    $copy = Mission::where('carried_from_id', $m->id)->firstOrFail();
    expect($copy->quarter_start->toDateString())->toBe('2027-01-04')->and($copy->status)->toBe('on_track');
});

test('milestone tasks cannot be due after the Mission quarter ends', function () {
    $t = orgTree();
    $m = mkMission($t['rep']);
    actAs($t['rep'])->post(ou('missions.milestones.store', [$m]), ['title' => 'Draft', 'due_date' => '2027-02-01'])->assertSessionHasErrors('due_date');
    actAs($t['rep'])->post(ou('missions.milestones.store', [$m]), ['title' => 'Draft', 'due_date' => '2026-12-01']);
    $ms = $m->milestones()->first();
    actAs($t['rep'])->post(ou('tasks.store'), ['source_type' => 'milestone', 'source_id' => $ms->id, 'title' => 'Write', 'output' => 'Draft doc', 'responsible_id' => $t['rep']->id, 'due_date' => '2027-01-10'])->assertSessionHasErrors('due_date');
});

test('meeting minutes scale and Solve gets the remainder', function () {
    expect(Meetings::minutes(90))->toBe([5, 5, 5, 5, 5, 60, 5])->and(array_sum(Meetings::minutes(60)))->toBe(60)->and(Meetings::minutes(120))->toBe([7, 7, 7, 7, 7, 78, 7])->and(Meetings::minutes(60))->toBe([3, 3, 3, 3, 3, 42, 3]);
});

test('a weekly meeting runs end to end: cadence, start, segments, rating, end, recap', function () {
    $t = orgTree();
    actAs($t['rep'])->put(ou('teams.cadence', [$t['sales']]), ['meeting_weekday' => 3, 'meeting_time' => '10:00', 'meeting_minutes' => 90])->assertForbidden();
    actAs($t['lead'])->put(ou('teams.cadence', [$t['sales']]), ['meeting_weekday' => 3, 'meeting_time' => '10:00', 'meeting_minutes' => 90])->assertSessionHasNoErrors();
    $event = Event::where('type', 'weekly_meeting')->orderBy('starts_at')->firstOrFail();
    expect($event->starts_at->setTimezone('Asia/Jakarta')->format('D H:i'))->toBe('Wed 10:00')->and(Event::where('type', 'weekly_meeting')->count())->toBeGreaterThan(20);

    $p = mkProblem($t['lead'], $t['sales']->id);
    app(Problems::class)->rank($p, 1);
    actAs($t['rep'])->post(ou('meetings.start', [$event]))->assertForbidden();
    actAs($t['lead'])->post(ou('meetings.start', [$event]))->assertRedirect();
    $meeting = Meeting::firstOrFail();
    expect(DB::table('meeting_attendance')->where('meeting_id', $meeting->id)->where('present', true)->count())->toBe(2);

    foreach (range(0, 6) as $seg) {
        actAs($t['lead'])->post(ou('meetings.segment', [$meeting]), ['segment' => $seg]);
        actAs($t['rep'])->get(ou('meetings.run', [$event]))->assertOk();
    }
    actAs($t['rep'])->post(ou('meetings.segment', [$meeting]), ['segment' => 1])->assertForbidden();
    actAs($t['rep'])->getJson(ou('meetings.state', [$meeting]))->assertJson(['segment' => 6, 'ended' => false]);
    actAs($t['rep'])->post(ou('meetings.headline', [$meeting]), ['kind' => 'customer', 'text' => 'Big client signed']);
    actAs($t['rep'])->post(ou('tasks.store'), ['source_type' => 'meeting', 'source_id' => $meeting->id, 'team_seat_id' => $t['sales']->id, 'title' => 'Send recap', 'output' => 'Recap', 'responsible_id' => $t['rep']->id, 'due_date' => '2026-11-12']);
    actAs($t['rep'])->post(ou('solve.start', [$p]), ['mode' => 'quick', 'meeting_id' => $meeting->id]);
    actAs($t['rep'])->post(ou('solve.quick', [$p]), ['statement' => 'Pricing page is unclear', 'solved' => 1, 'meeting_id' => $meeting->id]);
    actAs($t['rep'])->post(ou('meetings.rate', [$meeting]), ['score' => 8]);
    actAs($t['lead'])->post(ou('meetings.rate', [$meeting]), ['score' => 9]);
    actAs($t['lead'])->put(ou('meetings.notes', [$meeting]), ['cascade_message' => 'We signed a big client.']);
    $open = mkProblem($t['lead'], $t['sales']->id);
    app(Problems::class)->rank($open, 1);
    actAs($t['lead'])->post(ou('meetings.end', [$meeting]));

    $meeting->refresh();
    expect($meeting->ended_at)->not->toBeNull()->and($open->fresh()->rank)->toBeNull();
    expect($p->fresh()->status)->toBe('solved')->and($p->fresh()->meeting_id)->toBe($meeting->id);
    $recap = app(Meetings::class)->recap($meeting);
    expect($recap['rating'])->toBe(8.5)->and($recap['tasks']->pluck('title')->all())->toContain('Send recap')->and($recap['solved']->count())->toBe(1);
    actAs($t['rep'])->get(ou('meetings.run', [$event]))->assertOk()->assertSee('We signed a big client.')->assertSee('8.5');
    actAs($t['rep'])->get(ou('meetings.index', ['team' => $t['sales']->id]))->assertOk();
});

test('home shows numbers to enter, overdue tasks, and leader blocks', function () {
    $t = orgTree();
    $m = mkMetric($t);
    mkTask(['r' => $t['rep']->id, 'due_date' => '2026-11-01', 'title' => 'Late thing']);
    actAs($t['rep'])->get(ou('home'))->assertOk()->assertSee('Enter this week')->assertSee('Late thing')->assertSee('10 days overdue');
    actAs($t['lead'])->get(ou('home'))->assertOk()->assertSee('Across your teams')->assertSee('Qualified leads');
    $t['repSeat']->forceFill(['holder_id' => null])->save();
    app(Access::class)->refresh();
    actAs($t['lead'])->get(ou('home'))->assertSee('1 metric has no owner because Rep is vacant.');
});

test('phase 2 pages render', function () {
    $t = orgTree();
    $m = mkMetric($t);
    $p = mkProblem($t['lead'], $t['sales']->id);
    $mi = mkMission($t['rep'], $t['sales']->id);
    actAs($t['rep'])->postJson(ou('metrics.value', [$m]), ['week_start' => '2026-11-02', 'value' => '41']);
    foreach ([['metrics.index'], ['metrics.index', ['trailing' => 1]], ['metrics.show', [$m]], ['problems.index'], ['problems.show', [$p]], ['problems.create'],
        ['solve.index'], ['missions.index'], ['missions.index', ['view' => 'cascade']], ['missions.show', [$mi]], ['missions.create'], ['teams.index'],
        ['teams.show', [$t['sales']]], ['meetings.index'], ['numbers.index']] as $r) {
        actAs($t['rep'])->get(ou($r[0], $r[1] ?? []))->assertOk();
    }
    actAs($t['lead'])->get(ou('metrics.create', ['team' => $t['sales']->id]))->assertOk()->assertSee('Add a metric');
    actAs($t['rep'])->get(ou('metrics.create', ['team' => $t['sales']->id]))->assertForbidden();
});

test('metric notes from the cell, and metric order by the team leader', function () {
    $t = orgTree();
    $a = mkMetric($t);
    $b = mkMetric($t, ['name' => 'Demos']);
    actAs($t['rep'])->postJson(ou('metrics.value', [$a]), ['week_start' => '2026-11-02', 'note' => 'Holiday week', 'note_only' => true])->assertStatus(422);
    actAs($t['rep'])->postJson(ou('metrics.value', [$a]), ['week_start' => '2026-11-02', 'value' => '12']);
    actAs($t['rep'])->postJson(ou('metrics.value', [$a]), ['week_start' => '2026-11-02', 'note' => 'Holiday week', 'note_only' => true])->assertOk()->assertJson(['note' => 'Holiday week']);
    expect(MetricValue::first()->value)->toBe(12.0);

    actAs($t['rep'])->postJson(ou('metrics.order'), ['ids' => [$b->id, $a->id]])->assertForbidden();
    actAs($t['lead'])->postJson(ou('metrics.order'), ['ids' => [$b->id, $a->id]])->assertOk();
    expect(Metric::orderBy('sort_order')->pluck('id')->all())->toBe([$b->id, $a->id]);
});

test('meeting notes autosave for participants and are editable by the leader afterwards', function () {
    $t = orgTree();
    $event = mkEvent($t['lead'], 'team', ['type' => 'weekly_meeting', 'team_seat_id' => $t['sales']->id]);
    actAs($t['lead'])->post(ou('meetings.start', [$event]));
    $meeting = Meeting::firstOrFail();
    actAs($t['rep'])->patchJson(ou('meetings.notes', [$meeting]), ['notes' => 'Decided to hire'])->assertOk();
    actAs($t['ops'])->patchJson(ou('meetings.notes', [$meeting]), ['notes' => 'x'])->assertForbidden();
    actAs($t['lead'])->post(ou('meetings.end', [$meeting]));
    actAs($t['rep'])->put(ou('meetings.notes', [$meeting]), ['notes' => 'late edit'])->assertForbidden();
    actAs($t['lead'])->put(ou('meetings.notes', [$meeting]), ['notes' => 'Decided to hire two'])->assertRedirect();
    expect($meeting->fresh()->notes)->toBe('Decided to hire two');
});
