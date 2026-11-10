<?php

use App\Http\Controllers\Org\AlignmentController;
use App\Models\AlignmentRound;
use App\Models\Belief;
use App\Models\Fundamental;
use App\Models\Problem;
use App\Models\Procedure;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Fundamentals;
use App\Services\Jobs;
use App\Services\Procedures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(fn () => $this->travelTo(now()->parse('2026-11-11 03:00:00')));

function fullFundamentals(): array
{
    return [
        'why' => 'We exist to help small teams run well.', 'way' => 'We make the weekly rhythm effortless.',
        'objective_statement' => 'Run 10,000 teams on one shared rhythm.', 'objective_year' => 2036,
        'target_market' => 'Teams of 10 to 200 people.', 'uniques' => ['Simple', 'Calm', 'Exact'],
        'proven_process' => "Discover: listen\nSet up: seats and numbers\nRun: weekly meetings",
        'snapshot_date' => '2029-12-31', 'snapshot_revenue' => 'IDR 10B', 'snapshot_profit' => 'IDR 2B', 'snapshot_measurables' => 'Teams: 500',
        'picture' => "We serve 500 teams\nEvery team meets weekly\nChurn is under 3%",
        'annual_date' => '2026-12-31', 'annual_revenue' => 'IDR 2B', 'annual_profit' => 'IDR 300M', 'annual_measurables' => '',
        'goals' => "Ship the meeting runner\nSign 50 teams\nHire two people",
    ];
}

function addBeliefs(int $n): void
{
    foreach (range(1, $n) as $i) {
        Belief::create(['name' => 'Belief '.$i, 'description' => 'What it looks like '.$i, 'sort_order' => $i]);
    }
}

test('answer tables are anonymous: UUID keys, no timestamps, nothing that points at a person', function () {
    foreach (['alignment_answers', 'diagnostic_answers', 'assessment_answers'] as $table) {
        $cols = Schema::getColumnListing($table);
        expect($cols)->not->toContain('created_at')->not->toContain('updated_at')->not->toContain('member_id')->not->toContain('user_id')->not->toContain('assessor_id');
        expect(Schema::getColumnType($table, 'id'))->toBeIn(['char', 'uuid', 'varchar']);
        $fks = collect(Schema::getForeignKeys($table))->pluck('foreign_table')->all();
        expect($fks)->not->toContain('memberships')->not->toContain('users');
    }
});

test('publishing lists what is missing, then saves an immutable version with a beliefs snapshot', function () {
    $t = orgTree();
    addBeliefs(2);
    actAs($t['rep'])->get(ou('fundamentals.edit'))->assertForbidden();
    actAs($t['admin'])->post(ou('fundamentals.save'), ['why' => 'Too short', 'publish' => 1])->assertSessionHasErrors('publish');
    $problems = session('publish_problems');
    expect($problems['beliefs'])->toContain('Add 1 more belief.')->and($problems['main_stage'])->toContain('Add 3 more uniques.');
    expect(Fundamental::where('status', 'published')->count())->toBe(0);

    addBeliefs(1);
    actAs($t['admin'])->post(ou('fundamentals.save'), fullFundamentals() + ['publish' => 1])->assertRedirect(ou('fundamentals.show'));
    $v1 = Fundamental::where('status', 'published')->firstOrFail();
    expect($v1->version)->toBe(1)->and(count($v1->content['beliefs']))->toBe(3)->and($v1->content['main_stage']['proven_process'][1])->toBe(['title' => 'Set up', 'description' => 'seats and numbers']);

    Belief::first()->forceFill(['name' => 'Renamed'])->save();
    actAs($t['admin'])->get(ou('fundamentals.edit'))->assertOk();
    actAs($t['admin'])->postJson(ou('fundamentals.save'), array_merge(fullFundamentals(), ['why' => 'A changed reason for existing here.']))->assertJson(['saved' => true]);
    expect($v1->fresh()->content['why'])->toBe('We exist to help small teams run well.')->and($v1->fresh()->content['beliefs'][0]['name'])->toBe('Belief 1');
    actAs($t['admin'])->post(ou('fundamentals.save'), array_merge(fullFundamentals(), ['why' => 'A changed reason for existing here.', 'publish' => 1]));
    expect(Fundamental::where('status', 'published')->max('version'))->toBe(2);
    actAs($t['rep'])->get(ou('fundamentals.history', ['a' => 1, 'b' => 2]))->assertOk()->assertSee('Changed');
    actAs($t['rep'])->get(ou('fundamentals.show', ['page' => 'plan']))->assertOk()->assertSee('Ship the meeting runner');
});

test('alignment: one round at a time, one answer per member, anonymous rows, warnings and leadership-only comments', function () {
    $t = orgTree();
    addBeliefs(3);
    actAs($t['admin'])->post(ou('alignment.open'), ['days' => 14])->assertSessionHasErrors('days');
    actAs($t['admin'])->post(ou('fundamentals.save'), fullFundamentals() + ['publish' => 1]);
    actAs($t['rep'])->post(ou('alignment.open'), ['days' => 14])->assertForbidden();
    actAs($t['admin'])->post(ou('alignment.open'), ['days' => 2])->assertSessionHasErrors('days');
    actAs($t['admin'])->post(ou('alignment.open'), ['days' => 14])->assertSessionHasNoErrors();
    actAs($t['admin'])->post(ou('alignment.open'), ['days' => 14])->assertSessionHasErrors('days');
    $round = AlignmentRound::firstOrFail();
    expect($round->member_count)->toBe(4);

    actAs($t['rep'])->get(ou('home'))->assertSee('Alignment Check is open');
    $answer = ['committed' => 1, 'clear' => array_fill_keys(array_keys(Fundamentals::PARTS), 1), 'comment' => ['why_way' => 'The way part is vague']];
    $answer['clear']['main_stage'] = 0;
    actAs($t['rep'])->post(ou('alignment.answer', [$round]), $answer)->assertSessionHasNoErrors();
    actAs($t['rep'])->post(ou('alignment.answer', [$round]), $answer)->assertSessionHasErrors('committed');
    expect(DB::table('alignment_answers')->count())->toBe(6)->and(DB::table('alignment_completions')->count())->toBe(1);

    actAs($t['rep'])->get(ou('alignment.index'))->assertSee('Fewer than 3 people answered')->assertDontSee('The way part is vague');
    actAs($t['admin'])->get(ou('alignment.index'))->assertSee('The way part is vague')->assertSee('Rio Rep');
    $res = AlignmentController::results($round);
    expect($res['clear']['main_stage'])->toEqual(0)->and($res['clear']['beliefs'])->toEqual(100)->and($res['committed'])->toEqual(100);

    $id = DB::table('alignment_answers')->whereNotNull('comment')->value('id');
    actAs($t['admin'])->post(ou('alignment.problem', [$round]), ['answer' => $id]);
    expect(Problem::where('source_type', 'alignment')->where('horizon', 'long')->where('team_seat_id', $t['root']->id)->exists())->toBeTrue();

    actAs($t['admin'])->post(ou('alignment.remind', [$round]));
    actAs($t['admin'])->post(ou('alignment.remind', [$round]))->assertSessionHasErrors('remind');
    $this->travelTo(now()->addDays(15));
    app(Jobs::class)->closeAlignmentRounds();
    expect($round->fresh()->closed_at)->not->toBeNull();
});

function mkProcedure(array $t, array $attrs = []): Procedure
{
    $p = new Procedure(array_merge(['title' => 'Lead handoff', 'purpose' => 'Hand every lead to sales within a day.', 'team_seat_id' => $t['sales']->id,
        'owner_seat_id' => $t['repSeat']->id, 'audience' => 'team', 'review_every_months' => 6, 'body' => "# Steps\n- Qualify\n- Hand off"], $attrs));
    $p->save();

    return $p;
}

test('sync status fails for each condition separately', function () {
    $t = orgTree();
    $p = mkProcedure($t);
    $svc = app(Procedures::class);
    $f = app(Access::class)->formation();
    $today = Calendar::for($t['org'])->today();
    expect($svc->status($p, $f, $today)['reasons'])->toContain('Not published yet');

    actAs($t['lead'])->put(ou('procedures.update', [$p]), ['title' => $p->title, 'purpose' => $p->purpose, 'owner_seat_id' => $t['repSeat']->id, 'audience' => 'team', 'review_every_months' => 6, 'body' => $p->body, 'publish' => 1]);
    $p->refresh();
    expect($p->version)->toBe(1)->and($svc->audience($p, $f))->toEqualCanonicalizing([$t['lead']->id, $t['rep']->id]);
    $s = $svc->status($p, $f, $today);
    expect($s['reasons'])->toContain("2 of 2 haven't acknowledged v1")->toContain('No audit yet');

    actAs($t['rep'])->get(ou('home'))->assertSee('To read: Lead handoff v1.');
    actAs($t['ops'])->post(ou('procedures.acknowledge', [$p]))->assertForbidden();
    actAs($t['rep'])->post(ou('procedures.acknowledge', [$p]));
    actAs($t['lead'])->post(ou('procedures.acknowledge', [$p]));
    actAs($t['ops'])->post(ou('procedures.audit', [$p]), ['audited_on' => '2026-11-10', 'result' => 'followed'])->assertForbidden();
    actAs($t['rep'])->post(ou('procedures.audit', [$p]), ['audited_on' => '2026-11-12', 'result' => 'followed'])->assertSessionHasErrors('audited_on');
    actAs($t['rep'])->post(ou('procedures.audit', [$p]), ['audited_on' => '2026-11-10', 'result' => 'partly'])->assertSessionHasErrors('findings');
    actAs($t['rep'])->post(ou('procedures.audit', [$p]), ['audited_on' => '2026-11-10', 'result' => 'partly', 'findings' => 'Two leads waited'])->assertSessionHas('audit_followup');
    expect($svc->status($p, $f, $today)['reasons'])->toBe(['Last audit: partly followed']);

    actAs($t['rep'])->post(ou('procedures.audit', [$p]), ['audited_on' => '2026-11-11', 'result' => 'followed']);
    expect($svc->status($p, $f, $today)['in_sync'])->toBeTrue();

    // A new version needs new acknowledgements.
    actAs($t['rep'])->put(ou('procedures.update', [$p]), ['title' => $p->title, 'purpose' => $p->purpose, 'owner_seat_id' => $t['repSeat']->id, 'audience' => 'team', 'review_every_months' => 6, 'body' => 'v2 body', 'publish' => 1]);
    expect($svc->status($p->fresh(), $f, $today)['reasons'])->toBe(["2 of 2 haven't acknowledged v2"]);

    // Old audit and overdue review.
    $later = $today->addMonths(7);
    $s = $svc->status($p->fresh(), $f, $later);
    expect($s['reasons'])->toContain('Last audit is older than 6 months')->toContain('Review due');
    $this->travelTo(now()->addMonths(7));
    actAs($t['rep'])->post(ou('procedures.review', [$p]));
    expect($p->fresh()->reviewed_at->toDateString())->toBe(now()->setTimezone('Asia/Jakarta')->toDateString());
});

test('only the team leader or an Org Admin creates procedures; markdown is sanitized', function () {
    $t = orgTree();
    $d = ['team_seat_id' => $t['sales']->id, 'title' => 'Refunds', 'purpose' => 'How we handle refunds fast.', 'owner_seat_id' => $t['repSeat']->id, 'audience' => 'organization', 'review_every_months' => 12];
    actAs($t['rep'])->post(ou('procedures.store'), $d)->assertForbidden();
    actAs($t['admin'])->post(ou('procedures.store'), $d)->assertRedirect();
    actAs($t['lead'])->post(ou('procedures.store'), ['owner_seat_id' => $t['opsSeat']->id] + $d)->assertSessionHasErrors('owner_seat_id');
    $html = Procedures::render("<script>alert(1)</script>\n[x](javascript:alert(1)) ![i](http://e/x.png) **ok**");
    expect($html)->not->toContain('<script')->not->toContain('javascript:')->not->toContain('<img')->toContain('<strong>ok</strong>');
});

test('phase 3 pages render', function () {
    $t = orgTree();
    $p = mkProcedure($t);
    foreach (['fundamentals.show', 'fundamentals.history', 'alignment.index', 'procedures.index', 'sync.index'] as $r) {
        actAs($t['rep'])->get(ou($r))->assertOk();
    }
    actAs($t['rep'])->get(ou('procedures.show', [$p]))->assertOk();
    actAs($t['rep'])->get(ou('procedures.edit', [$p]))->assertOk();
    actAs($t['admin'])->get(ou('fundamentals.edit'))->assertOk();
    actAs($t['lead'])->get(ou('procedures.create', ['team' => $t['sales']->id]))->assertOk();
});
