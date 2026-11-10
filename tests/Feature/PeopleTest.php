<?php

use App\Http\Controllers\Org\DiagnosticController;
use App\Http\Controllers\Org\EvaluatorController;
use App\Models\AssessmentAssessor;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentType;
use App\Models\Belief;
use App\Models\DiagnosticCycle;
use App\Models\Evaluation;
use App\Models\Event;
use App\Models\Problem;
use App\Services\Assessments;
use App\Services\Jobs;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->travelTo(now()->parse('2026-11-11 03:00:00')));

function beliefs3(): array
{
    return collect(['Own it', 'Tell the truth', 'Finish'])->map(fn ($n, $i) => Belief::create(['name' => $n, 'sort_order' => $i]))->all();
}

function evaluation(array $b, string $s1 = 'plus', string $s2 = 'plus', string $s3 = 'plus', bool $fit = true): array
{
    return ['scores' => [$b[0]->id => $s1, $b[1]->id => $s2, $b[2]->id => $s3], 'understands' => 1, 'wants' => $fit ? 1 : 0, 'capacity' => 1];
}

test('evaluator result rules follow the organization bar', function () {
    [$org] = makeOrg();
    expect(EvaluatorController::result(['plus', 'mixed', 'plus'], true, true, true, $org)['label'])->toBe('Right person, right seat');
    expect(EvaluatorController::result(['mixed', 'mixed'], true, true, true, $org)['label'])->toBe('Wrong person, right seat');
    expect(EvaluatorController::result(['minus'], true, true, true, $org)['person'])->toBeFalse();
    expect(EvaluatorController::result(['plus'], true, false, true, $org)['label'])->toBe('Right person, wrong seat');
    $org->forceFill(['evaluator_bar_max_minus' => 1, 'evaluator_bar_max_mixed' => 2])->save();
    expect(EvaluatorController::result(['minus', 'mixed', 'mixed'], true, true, true, $org)['person'])->toBeTrue();
});

test('evaluations: who may create, who may see, same-day edit window', function () {
    $t = orgTree();
    $b = beliefs3();
    actAs($t['ops'])->post(ou('evaluator.store'), ['seat_id' => $t['repSeat']->id] + evaluation($b))->assertForbidden();
    actAs($t['rep'])->post(ou('evaluator.store'), ['seat_id' => $t['sales']->id] + evaluation($b))->assertForbidden();
    actAs($t['lead'])->post(ou('evaluator.store'), ['seat_id' => $t['repSeat']->id, 'scores' => [$b[0]->id => 'plus']])->assertSessionHasErrors(['scores.'.$b[1]->id, 'understands']);
    actAs($t['lead'])->post(ou('evaluator.store'), ['seat_id' => $t['repSeat']->id] + evaluation($b, 'mixed', 'mixed'))->assertRedirect();
    $e = Evaluation::firstOrFail();
    expect($e->subject_id)->toBe($t['rep']->id)->and($e->scores()->count())->toBe(3);
    // The CEO holds an ancestor of Rep's seat too.
    actAs($t['admin'])->post(ou('evaluator.store'), ['seat_id' => $t['repSeat']->id] + evaluation($b))->assertRedirect();

    actAs($t['rep'])->get(ou('evaluator.person', ['membership' => $t['rep']->id]))->assertOk()->assertSee('Wrong person, right seat')->assertSee('Lena Lead');
    actAs($t['lead'])->get(ou('evaluator.person', ['membership' => $t['rep']->id]))->assertOk();
    actAs($t['ops'])->get(ou('evaluator.person', ['membership' => $t['rep']->id]))->assertForbidden();
    $admin2 = makeMember('Ola Admin', true);
    actAs($admin2)->get(ou('evaluator.person', ['membership' => $t['rep']->id]))->assertOk();

    actAs($t['admin'])->get(ou('evaluator.edit', [$e]))->assertForbidden();
    actAs($t['lead'])->put(ou('evaluator.update', [$e]), evaluation($b))->assertRedirect();
    expect($e->scores()->pluck('score')->unique()->all())->toBe(['plus']);
    $this->travelTo(now()->parse('2026-11-11 17:30:00'));
    actAs($t['lead'])->put(ou('evaluator.update', [$e]), evaluation($b))->assertForbidden();

    $b[0]->forceFill(['archived_at' => now()])->save();
    actAs($t['rep'])->get(ou('evaluator.person', ['membership' => $t['rep']->id]))->assertSee('Archived belief');
    actAs($t['lead'])->get(ou('evaluator.index'))->assertOk()->assertSee('The bar: no Rarely, at most 1 Sometimes.')->assertDontSee('Own it');
});

test('diagnostic: anonymous answers, results hidden until close, score formulas with fixed data', function () {
    $t = orgTree();
    actAs($t['rep'])->post(ou('diagnostic.open'), ['days' => 14])->assertForbidden();
    actAs($t['admin'])->post(ou('diagnostic.open'), ['days' => 5])->assertSessionHasErrors('days');
    actAs($t['admin'])->post(ou('diagnostic.open'), ['days' => 14])->assertSessionHasNoErrors();
    $c = DiagnosticCycle::firstOrFail();
    expect(Event::where('type', 'diagnostic')->count())->toBe(1);

    $keys = array_keys(config('diagnostic.statements'));
    $all = fn ($v) => ['answers' => array_fill_keys($keys, $v)];
    actAs($t['rep'])->post(ou('diagnostic.answer', [$c]), ['answers' => ['q1' => 5]])->assertSessionHasErrors('answers.q2');
    $a = $all(5);
    $a['answers']['q18'] = 1;
    actAs($t['rep'])->post(ou('diagnostic.answer', [$c]), $a)->assertSessionHasNoErrors();
    actAs($t['rep'])->post(ou('diagnostic.answer', [$c]), $a)->assertSessionHasErrors('answers');
    actAs($t['lead'])->post(ou('diagnostic.answer', [$c]), $all(3));
    expect(DB::table('diagnostic_answers')->count())->toBe(40);
    actAs($t['rep'])->get(ou('diagnostic.index'))->assertDontSee('Biggest gaps');

    $this->travelTo(now()->addDays(15));
    app(Jobs::class)->closeDiagnosticCycles();
    $r = DiagnosticController::results($c->fresh());
    // Sums: rep 19×5 + 1 = 96, lead 20×3 = 60 → overall (96+60)/2 = 78 of 100.
    // Direction: six statements averaging 4 → 24 of 30 = 80. Measurements: 4+2+4+4 = 14 of 20 = 70. q18 averages 2 → 40.
    expect($r['responses'])->toBe(2)->and($r['sections']['direction'])->toBe(80)->and($r['sections']['measurements'])->toBe(70);
    expect($r['overall'])->toBe(78)->and($r['gaps'][0]['key'])->toBe('q18')->and($r['gaps'][0]['score'])->toBe(40);
    actAs($t['rep'])->get(ou('diagnostic.index'))->assertSee('Biggest gaps')->assertSee('Fewer than 3 people answered');

    actAs($t['rep'])->post(ou('diagnostic.problem', [$c]), ['key' => 'q18'])->assertForbidden();
    actAs($t['admin'])->post(ou('diagnostic.problem', [$c]), ['key' => 'measurements'])->assertRedirect();
    expect(Problem::where('source_type', 'diagnostic')->where('horizon', 'long')->exists())->toBeTrue();
});

test('360: default assessors, anonymous answers, results only after close and only for permitted people', function () {
    $t = orgTree();
    $type = AssessmentType::create(['name' => 'Direction', 'sort_order' => 1]);
    $i1 = $type->items()->create(['text' => 'Gives clear direction', 'sort_order' => 1]);
    $i2 = $type->items()->create(['text' => 'Follows through on commitments', 'sort_order' => 2]);
    actAs($t['rep'])->post(ou('assessment.cycles.store'), ['name' => 'Q4 360', 'opens_on' => '2026-11-12', 'closes_on' => '2026-11-30'])->assertForbidden();
    actAs($t['admin'])->post(ou('assessment.cycles.store'), ['name' => 'Q4 360', 'opens_on' => '2026-11-12', 'closes_on' => '2026-11-30'])->assertRedirect();
    $c = AssessmentCycle::firstOrFail();
    actAs($t['admin'])->post(ou('assessment.matrix', [$c]), ['cells' => [$t['lead']->id => [$type->id]]]);
    $a = AssessmentAssignment::firstOrFail();
    // Lead holds Sales Lead: self, parent (CEO = admin), sibling (Ops Lead), child (Rep).
    expect($a->assessors()->pluck('assessor_id')->all())->toEqualCanonicalizing([$t['lead']->id, $t['admin']->id, $t['ops']->id, $t['rep']->id]);
    actAs($t['admin'])->get(ou('assessment.cycle', [$c]))->assertOk()->assertDontSee('Fewer than 3 assessors');

    $this->travelTo(now()->parse('2026-11-12 03:00:00'));
    actAs($t['rep'])->get(ou('home'))->assertOk();
    expect($c->fresh()->status)->toBe('open');
    actAs($t['rep'])->get(ou('home'))->assertSee('1 assessment to complete.');

    $slot = fn ($m) => AssessmentAssessor::where('assignment_id', $a->id)->where('assessor_id', $m->id)->first();
    actAs($t['rep'])->post(ou('assessment.answer.store', [$slot($t['rep'])]), ['scores' => [$i1->id => 4.1, $i2->id => 3]])->assertSessionHasErrors('scores.'.$i1->id);
    actAs($t['rep'])->post(ou('assessment.answer.store', [$slot($t['rep'])]), ['scores' => [$i1->id => 4, $i2->id => 3]])->assertSessionHasNoErrors();
    actAs($t['ops'])->post(ou('assessment.answer.store', [$slot($t['ops'])]), ['scores' => [$i1->id => 3, $i2->id => 3.5]]);
    actAs($t['admin'])->post(ou('assessment.answer.store', [$slot($t['admin'])]), ['scores' => [$i1->id => 5, $i2->id => 4]]);
    actAs($t['lead'])->post(ou('assessment.answer.store', [$slot($t['lead'])]), ['scores' => [$i1->id => 5, $i2->id => 5]]);
    actAs($t['ops'])->post(ou('assessment.answer.store', [$slot($t['rep'])]), ['scores' => [$i1->id => 1, $i2->id => 1]])->assertNotFound();
    expect(DB::table('assessment_answers')->count())->toBe(8)->and(DB::table('assessment_answers')->where('is_self', true)->count())->toBe(2);

    actAs($t['admin'])->delete(ou('assessment.assessors.destroy', [$c, $slot($t['rep'])]))->assertSessionHasErrors('assessor');
    actAs($t['admin'])->post(ou('assessment.matrix', [$c]), ['cells' => []]);
    expect(AssessmentAssignment::count())->toBe(1);
    actAs($t['admin'])->delete(ou('assessment.destroy', [$c]))->assertSessionHasErrors('cycle');

    actAs($t['lead'])->get(ou('assessment.person', ['membership' => $t['lead']->id]))->assertOk()->assertSee('No closed cycles yet');
    $this->travelTo(now()->parse('2026-12-01 03:00:00'));
    app(Assessments::class)->advance($t['org']);
    expect($c->fresh()->status)->toBe('closed');

    $r = app(Assessments::class)->results($c->fresh(), $t['lead']->id);
    // Others: item1 (4+3+5)/3 = 4.00; item2 (3+3.5+4)/3 = 3.50; type 3.75. Self 5.00.
    expect($r['types'][0]['items'][0]['others'])->toBe(4.0)->and($r['types'][0]['items'][1]['others'])->toBe(3.5)->and($r['overall'])->toBe(3.75)->and($r['self'])->toBe(5.0);
    actAs($t['lead'])->get(ou('assessment.person', ['membership' => $t['lead']->id]))->assertOk()->assertSee('3.75')->assertSee('+1.50');
    actAs($t['admin'])->get(ou('assessment.person', ['membership' => $t['lead']->id]))->assertOk();
    actAs($t['rep'])->get(ou('assessment.person', ['membership' => $t['lead']->id]))->assertForbidden();
    actAs($t['ops'])->get(ou('assessment.person', ['membership' => $t['lead']->id]))->assertForbidden();
});

test('a cycle without answers can be deleted by an Org Admin only', function () {
    $t = orgTree();
    $c = AssessmentCycle::create(['name' => 'Draft', 'opens_on' => '2027-01-10', 'closes_on' => '2027-01-20']);
    actAs($t['lead'])->delete(ou('assessment.destroy', [$c]))->assertForbidden();
    actAs($t['admin'])->delete(ou('assessment.destroy', [$c]))->assertRedirect();
    expect(AssessmentCycle::count())->toBe(0);
});

test('phase 4 pages render', function () {
    $t = orgTree();
    beliefs3();
    foreach (['evaluator.index', 'diagnostic.index', 'assessment.index'] as $r) {
        actAs($t['rep'])->get(ou($r))->assertOk();
    }
    actAs($t['lead'])->get(ou('evaluator.create', ['seat' => $t['repSeat']->id]))->assertOk()->assertSee('Seat Fit Check');
    actAs($t['admin'])->get(ou('assessment.setup'))->assertOk();
    actAs($t['rep'])->get(ou('assessment.setup'))->assertForbidden();
});

test('360 types and items can be reordered by leadership', function () {
    $t = orgTree();
    $a = AssessmentType::create(['name' => 'Vision', 'sort_order' => 1]);
    $b = AssessmentType::create(['name' => 'People', 'sort_order' => 2]);
    $i1 = $a->items()->create(['text' => 'Sets a clear direction', 'sort_order' => 1]);
    $i2 = $a->items()->create(['text' => 'Explains the why', 'sort_order' => 2]);
    actAs($t['rep'])->postJson(ou('assessment.order'), ['kind' => 'types', 'ids' => [$b->id, $a->id]])->assertForbidden();
    actAs($t['admin'])->postJson(ou('assessment.order'), ['kind' => 'types', 'ids' => [$b->id, $a->id]])->assertOk();
    actAs($t['admin'])->postJson(ou('assessment.order'), ['kind' => 'items', 'type' => $a->id, 'ids' => [$i2->id, $i1->id]])->assertOk();
    expect(AssessmentType::whereIn('id', [$a->id, $b->id])->orderBy('sort_order')->pluck('id')->all())->toBe([$b->id, $a->id]);
    expect($a->items()->pluck('id')->all())->toBe([$i2->id, $i1->id]);
    actAs($t['admin'])->postJson(ou('assessment.order'), ['kind' => 'items', 'type' => $b->id, 'ids' => [$i1->id]])->assertStatus(422);
});

test('new organizations start with the leadership roles, and assessors see the level descriptions of each item', function () {
    $t = orgTree();
    $types = AssessmentType::with('items')->orderBy('sort_order')->get();
    expect($types->pluck('name')->first())->toBe('Role 1 - Individual Contributor')->and($types)->toHaveCount(6)
        ->and($types->sum(fn ($ty) => $ty->items->count()))->toBe(47)
        ->and($types->every(fn ($ty) => $ty->items->every(fn ($i) => count($i->levels) === 5)))->toBeTrue();

    $type = $types->first();
    $c = AssessmentCycle::create(['name' => 'Q4 360', 'opens_on' => '2026-11-12', 'closes_on' => '2026-11-30']);
    actAs($t['admin'])->post(ou('assessment.matrix', [$c]), ['cells' => [$t['lead']->id => [$type->id]]]);
    $this->travelTo(now()->parse('2026-11-12 03:00:00'));
    app(Assessments::class)->advance($t['org']);
    $slot = AssessmentAssessor::where('assessor_id', $t['rep']->id)->firstOrFail();
    actAs($t['rep'])->get(ou('assessment.answer', [$slot]))->assertOk()
        ->assertSee($type->items->first()->text)->assertSee($type->items->first()->levels[4]);
});
