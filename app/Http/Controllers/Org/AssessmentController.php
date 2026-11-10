<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAssessor;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentItem;
use App\Models\AssessmentType;
use App\Models\Membership;
use App\Models\RubricLevel;
use App\Services\Access;
use App\Services\Assessments;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Leadership Assessment 360 (PRD LA-01 to LA-07). */
class AssessmentController extends Controller
{
    public function index(Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $me = $access->id();
        $todo = AssessmentAssessor::with(['assignment.subject.user', 'assignment.type', 'assignment.cycle'])->where('assessor_id', $me)->whereNull('completed_at')
            ->whereHas('assignment.cycle', fn ($q) => $q->where('status', 'open'))->get();
        $cycles = AssessmentCycle::withCount('assignments')->orderByDesc('opens_on')->get();
        $visible = Membership::active()->with('user')->get()->filter(fn ($m) => $access->seesPerson($m->id))->sortBy('name');

        return view('assessment.index', ['todo' => $todo, 'cycles' => $cycles, 'canLead' => auth()->user()->can('lead', $tenant->organization), 'people' => $visible]);
    }

    public function setup(Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);

        return view('assessment.setup', ['types' => AssessmentType::with('items')->orderBy('sort_order')->get(), 'levels' => RubricLevel::orderBy('score')->get()]);
    }

    public function saveSetup(Request $request, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        $d = $request->validate([
            'action' => 'required|in:type,item,archive_type,archive_item,level',
            'name' => 'required_if:action,type|nullable|string|min:2|max:80', 'type_id' => 'required_if:action,item|nullable|integer',
            'text' => 'required_if:action,item|nullable|string|min:5|max:255', 'id' => 'nullable|integer',
            'label' => 'required_if:action,level|nullable|string|max:40', 'description' => 'nullable|string|max:255',
        ]);
        match ($d['action']) {
            'type' => AssessmentType::create(['name' => $d['name'], 'sort_order' => (int) AssessmentType::max('sort_order') + 1]),
            'item' => AssessmentType::findOrFail($d['type_id'])->items()->create(['text' => $d['text'], 'sort_order' => (int) AssessmentItem::where('type_id', $d['type_id'])->max('sort_order') + 1]),
            'archive_type' => tap(AssessmentType::findOrFail($d['id']), fn ($t) => $t->forceFill(['archived_at' => $t->archived_at ? null : now()])->save()),
            'archive_item' => tap(AssessmentItem::whereIn('type_id', AssessmentType::select('id'))->findOrFail($d['id']), fn ($i) => $i->forceFill(['archived_at' => $i->archived_at ? null : now()])->save()),
            'level' => RubricLevel::findOrFail($d['id'])->fill(['label' => $d['label'], 'description' => $d['description'] ?? null])->save(),
        };

        return back()->with('status', 'Saved');
    }

    /** LA-01: types and items are ordered; the order is saved from drag and drop or the arrow buttons. */
    public function order(Request $request, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        $d = $request->validate(['kind' => 'required|in:types,items', 'type' => 'required_if:kind,items|integer', 'ids' => 'required|array|max:200', 'ids.*' => 'integer']);
        $ids = array_map('intval', $d['ids']);
        $query = $d['kind'] === 'types'
            ? AssessmentType::query()
            : AssessmentItem::where('type_id', AssessmentType::findOrFail($d['type'])->id);
        abort_unless($query->clone()->whereIn('id', $ids)->count() === count($ids), 422);
        foreach ($ids as $i => $id) {
            $query->clone()->whereKey($id)->update(['sort_order' => $i]);
        }

        return response()->json(['saved' => true]);
    }

    public function storeCycle(Request $request, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        $d = $request->validate(['name' => 'required|string|min:2|max:120', 'opens_on' => 'required|date', 'closes_on' => 'required|date|after:opens_on']);
        $c = AssessmentCycle::create($d);

        return redirect()->route('org.assessment.cycle', $c)->with('status', 'Cycle created. Choose who is assessed on what');
    }

    public function cycle(AssessmentCycle $cycle, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $canLead = auth()->user()->can('lead', $tenant->organization);
        $assignments = $cycle->assignments()->with(['assessors.assessor.user', 'subject.user', 'type'])->get();

        return view('assessment.cycle', [
            'cycle' => $cycle, 'assignments' => $assignments, 'canLead' => $canLead,
            'members' => Membership::active()->with('user')->get()->sortBy('name'), 'types' => AssessmentType::whereNull('archived_at')->orderBy('sort_order')->get(),
            'answered' => DB::table('assessment_answers')->whereIn('assignment_id', $assignments->pluck('id'))->distinct()->pluck('assignment_id')->all(),
            'memberOptions' => Tasks::memberOptions(), 'seesPerson' => fn ($id) => $access->seesPerson($id),
        ]);
    }

    /** LA-02 matrix: ticked cells become assignments; unticked ones are removed if they have no answers. */
    public function matrix(Request $request, AssessmentCycle $cycle, Assessments $svc, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        abort_if($cycle->status === 'closed', 422);
        $cells = $request->validate(['cells' => 'array', 'cells.*' => 'array', 'cells.*.*' => 'integer'])['cells'] ?? [];
        $members = Membership::active()->pluck('id')->all();
        $types = AssessmentType::whereNull('archived_at')->pluck('id')->all();
        DB::transaction(function () use ($cycle, $cells, $members, $types, $svc) {
            $want = [];
            foreach ($cells as $subject => $typeIds) {
                foreach ($typeIds as $type) {
                    if (in_array((int) $subject, $members, true) && in_array((int) $type, $types, true)) {
                        $want[] = $subject.':'.$type;
                        $svc->assign($cycle, (int) $subject, (int) $type);
                    }
                }
            }
            foreach ($cycle->assignments()->get() as $a) {
                if (! in_array($a->subject_id.':'.$a->type_id, $want, true) && ! Assessments::hasAnswers($a)) {
                    $a->assessors()->delete();
                    $a->delete();
                }
            }
        });

        return back()->with('status', 'Assignments saved');
    }

    public function addAssessor(Request $request, AssessmentCycle $cycle, AssessmentAssignment $assignment, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        abort_unless($assignment->cycle_id === $cycle->id && $cycle->status !== 'closed', 404);
        $id = (int) $request->validate(['assessor_id' => 'required|integer'])['assessor_id'];
        abort_unless(Membership::active()->whereKey($id)->exists(), 422);
        $assignment->assessors()->firstOrCreate(['assessor_id' => $id], ['is_self' => $id === $assignment->subject_id]);

        return back()->with('status', 'Assessor added');
    }

    public function removeAssessor(AssessmentCycle $cycle, AssessmentAssessor $assessor, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        abort_unless($assessor->assignment->cycle_id === $cycle->id, 404);
        if ($assessor->completed_at) {
            throw ValidationException::withMessages(['assessor' => 'This person already answered, so they stay.']);
        }
        $assessor->delete();

        return back()->with('status', 'Assessor removed');
    }

    public function openNow(AssessmentCycle $cycle, Assessments $svc, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        abort_unless($cycle->status === 'draft', 422);
        if (! $cycle->assignments()->exists()) {
            throw ValidationException::withMessages(['cycle' => 'Assign at least one person first.']);
        }
        $svc->open($cycle);

        return back()->with('status', 'Cycle opened. Assessors see it on Home');
    }

    /** LA-07: deletable only while no answer exists; Org Admin only (section 5). */
    public function destroy(AssessmentCycle $cycle, Tenant $tenant)
    {
        $this->authorize('manage', $tenant->organization);
        $ids = $cycle->assignments()->pluck('id');
        if (DB::table('assessment_answers')->whereIn('assignment_id', $ids)->exists()) {
            throw ValidationException::withMessages(['cycle' => 'Someone already answered, so this cycle can\'t be deleted.']);
        }
        DB::transaction(function () use ($cycle, $ids) {
            AssessmentAssessor::whereIn('assignment_id', $ids)->delete();
            AssessmentAssignment::whereIn('id', $ids)->delete();
            $cycle->delete();
        });

        return redirect()->route('org.assessment.index')->with('status', 'Cycle deleted');
    }

    public function answerForm(AssessmentAssessor $assessor, Access $access)
    {
        abort_unless($access->owner($assessor->assessor_id) && ! $assessor->completed_at, 404);
        $a = $assessor->assignment()->with(['cycle', 'subject.user', 'type.items'])->first();
        abort_unless($a && $a->cycle && $a->cycle->status === 'open', 404);

        return view('assessment.answer', ['assessor' => $assessor, 'a' => $a, 'items' => $a->type->items->whereNull('archived_at'), 'levels' => RubricLevel::orderBy('score')->get()]);
    }

    public function answer(Request $request, AssessmentAssessor $assessor, Access $access)
    {
        abort_unless($access->owner($assessor->assessor_id), 404);
        $a = $assessor->assignment()->with(['cycle', 'type.items'])->first();
        abort_unless($a && $a->cycle && $a->cycle->status === 'open', 422);
        $items = $a->type->items->whereNull('archived_at');
        $rules = [];
        foreach ($items as $item) {
            $rules['scores.'.$item->id] = 'required|numeric|between:1,5|multiple_of:0.25';
        }
        $d = $request->validate($rules, ['scores.*.required' => 'Rate this item.', 'scores.*.multiple_of' => 'Use steps of 0.25.', 'scores.*.between' => 'Use a score from 1.00 to 5.00.']);
        // Completion and answers in one transaction; answer rows never reference the assessor (LA-04).
        DB::transaction(function () use ($assessor, $a, $items, $d) {
            $fresh = AssessmentAssessor::whereKey($assessor->id)->lockForUpdate()->first();
            if ($fresh->completed_at) {
                throw ValidationException::withMessages(['scores' => 'You already answered.']);
            }
            $fresh->completed_at = now();
            $fresh->save();
            $rows = $items->map(fn ($i) => ['id' => (string) Str::uuid(), 'assignment_id' => $a->id, 'item_id' => $i->id, 'score' => round((float) $d['scores'][$i->id], 2), 'is_self' => $fresh->is_self])->values()->all();
            DB::table('assessment_answers')->insert($rows);
        });

        return redirect()->route('org.assessment.index')->with('status', 'Answers sent');
    }

    public function person(Membership $membership, Access $access, Assessments $svc, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        abort_unless($access->seesPerson($membership->id), 403);
        $cycles = AssessmentCycle::where('status', 'closed')->whereHas('assignments', fn ($q) => $q->where('subject_id', $membership->id))->orderBy('closes_on')->get();
        $results = $cycles->mapWithKeys(fn ($c) => [$c->id => $svc->results($c, $membership->id)]);
        $typeNames = collect($results)->flatMap(fn ($r) => collect($r['types'])->pluck('name'))->unique()->values();

        return view('assessment.person', ['subject' => $membership->load('user'), 'cycles' => $cycles, 'results' => $results, 'typeNames' => $typeNames,
            'open' => AssessmentCycle::whereIn('status', ['draft', 'open'])->whereHas('assignments', fn ($q) => $q->where('subject_id', $membership->id))->exists()]);
    }
}
