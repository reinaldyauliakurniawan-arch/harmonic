<?php

use App\Models\AlignmentRound;
use App\Models\AssessmentCycle;
use App\Models\Belief;
use App\Models\Board;
use App\Models\DiagnosticCycle;
use App\Models\Evaluation;
use App\Models\Fundamental;
use App\Models\Meeting;
use App\Models\Metric;
use App\Models\Procedure;
use App\Services\Onboarding;

test('every tenant record of another organization returns 404, never 403', function () {
    $a = orgTree();
    $board = new Board(['title' => 'A board']);
    $board->created_by = $a['admin']->id;
    $board->save();
    $metric = new Metric(['name' => 'M', 'team_seat_id' => $a['sales']->id, 'owner_seat_id' => $a['repSeat']->id, 'unit' => 'number', 'operator' => 'gte', 'goal_value' => 1]);
    $metric->save();
    $procedure = new Procedure(['title' => 'P', 'purpose' => 'Purpose text here', 'team_seat_id' => $a['sales']->id, 'owner_seat_id' => $a['repSeat']->id]);
    $procedure->save();
    $belief = Belief::create(['name' => 'B']);
    $f = new Fundamental(['content' => []]);
    $f->forceFill(['version' => 1, 'status' => 'published'])->save();
    $round = new AlignmentRound;
    $round->forceFill(['fundamentals_id' => $f->id, 'opened_by' => $a['admin']->id, 'opened_at' => now(), 'closes_at' => now()->addDays(3)])->save();
    $cycle = new DiagnosticCycle;
    $cycle->forceFill(['opened_by' => $a['admin']->id, 'opened_at' => now(), 'closes_at' => now()->addDays(9)])->save();
    $ac = AssessmentCycle::create(['name' => 'C', 'opens_on' => '2027-01-01', 'closes_on' => '2027-01-10']);
    $ev = new Evaluation(['understands' => true, 'wants' => true, 'capacity' => true]);
    $ev->forceFill(['subject_id' => $a['rep']->id, 'seat_id' => $a['repSeat']->id, 'evaluator_id' => $a['lead']->id, 'evaluated_on' => now()->toDateString()])->save();
    $event = mkEvent($a['admin'], 'org', ['type' => 'weekly_meeting', 'team_seat_id' => $a['sales']->id]);
    $meeting = new Meeting(['team_seat_id' => $a['sales']->id, 'type' => 'weekly', 'event_id' => $event->id, 'scheduled_for' => now()]);
    $meeting->forceFill(['started_at' => now()])->save();
    $task = mkTask(['r' => $a['rep']->id]);
    $mission = mkMission($a['rep']);
    $problem = mkProblem($a['lead'], $a['sales']->id);
    [$inv] = app(Onboarding::class)->invite($a['org'], 'x@example.com', false, null, $a['admin']->id);

    [$orgB, $adminB] = makeOrg('Beta');
    $gets = [
        ['tasks.show', [$task]], ['boards.show', [$board]], ['metrics.show', [$metric]], ['missions.show', [$mission]],
        ['problems.show', [$problem]], ['procedures.show', [$procedure]], ['meetings.state', [$meeting]], ['meetings.run', [$event]],
        ['formation.edit', [$a['repSeat']]], ['teams.show', [$a['sales']]], ['evaluator.person', ['membership' => $a['rep']->id]],
        ['evaluator.edit', [$ev]], ['assessment.cycle', [$ac]], ['assessment.person', ['membership' => $a['rep']->id]],
    ];
    foreach ($gets as [$route, $params]) {
        actAs($adminB)->get(ou($route, $params))->assertNotFound();
    }
    $posts = [
        ['people.admin', [$a['rep']]], ['people.revoke', [$inv], 'delete'], ['beliefs.update', [$belief], 'put'],
        ['alignment.close', [$round]], ['diagnostic.answer', [$cycle]], ['calendar.notes', [$event], 'patch'],
        ['metrics.value', [$metric]], ['solve.start', [$problem]], ['missions.status', [$mission]],
    ];
    foreach ($posts as $p) {
        $method = $p[2] ?? 'post';
        actAs($adminB)->{$method}(ou($p[0], $p[1]), [])->assertNotFound();
    }
});
