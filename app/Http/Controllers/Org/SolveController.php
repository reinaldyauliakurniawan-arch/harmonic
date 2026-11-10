<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Problem;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Problems;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** I-S.O.L.V.E (PRD S-01 to S-04). */
class SolveController extends Controller
{
    public const STEPS = [
        1 => ['Ignite', 'ignite', 'Who needs to be here, and how long will you give this?'],
        2 => ['State', 'statement', 'Say the problem in one sentence. Not the symptom, not the fix.'],
        3 => ['Observe', 'observe', 'Ask why until you reach a cause you can act on.'],
        4 => ['Leverage', 'leverage', 'Which belief, procedure, or proven approach applies here?'],
        5 => ['Validate', 'solution', 'What will you do, and who owns it?'],
        6 => ['Evaluate', 'evaluate_on', 'Did it work?'],
    ];

    public function index(Access $access, Tenant $tenant)
    {
        $this->authorize('create', Problem::class);
        $f = $access->formation();
        $mine = $f->teamIdsOf($access->id());
        $today = Calendar::for($tenant->organization)->today()->toDateString();
        $solving = Problem::with(['solve', 'owner.user'])->where('status', 'solving')->orderByRaw('team_seat_id in ('.(implode(',', array_map('intval', $mine)) ?: '0').') desc')->latest('updated_at')->get();
        $evaluate = Problem::with(['solve', 'owner.user'])->where('status', 'solved')->whereHas('solve', fn ($q) => $q->whereNull('evaluation_result')->whereNotNull('evaluate_on')->where('evaluate_on', '<=', $today))->get();

        return view('solve.index', ['solving' => $solving, 'evaluate' => $evaluate, 'teams' => $f->teamOptions()]);
    }

    private function meetingFor(Request $request, Problem $p): ?int
    {
        $id = $request->input('meeting_id');
        if (! $id) {
            return null;
        }
        $m = Meeting::whereKey($id)->where('team_seat_id', $p->team_seat_id)->whereNotNull('started_at')->whereNull('ended_at')->first();

        return $m?->id;
    }

    private function back(Request $request, Problem $p)
    {
        $mid = $request->input('meeting_id');
        $m = $mid ? Meeting::find($mid) : null;

        return $m && $m->event_id ? redirect()->route('org.meetings.run', ['event' => $m->event_id, 'problem' => $p->id]) : redirect()->route('org.problems.show', $p);
    }

    public function start(Request $request, Problem $problem, Problems $problems)
    {
        $this->authorize('solve', $problem);
        abort_unless(in_array($problem->status, ['open', 'solving'], true), 422);
        $mode = $request->validate(['mode' => 'required|in:quick,full'])['mode'];
        $problems->start($problem, $mode, $this->meetingFor($request, $problem));

        return $this->back($request, $problem);
    }

    /** Quick mode (S-02): statement and solution, then Mark solved. */
    public function quick(Request $request, Problem $problem, Problems $problems)
    {
        $this->authorize('solve', $problem);
        abort_unless($problem->status === 'solving' && $problem->solve?->mode === 'quick', 422);
        $d = $request->validate(['statement' => 'required|string|min:10|max:280', 'solution' => 'nullable|string|max:2000', 'solved' => 'boolean']);
        $problem->solve->fill(['statement' => $d['statement'], 'solution' => $d['solution'] ?? null])->save();
        if ($d['solved'] ?? false) {
            $problems->markSolved($problem, $this->meetingFor($request, $problem));
        }

        return $this->back($request, $problem)->with('status', ($d['solved'] ?? false) ? 'Solved' : 'Saved');
    }

    /** Full mode (S-03): each step saves on Next; finishing step 5 marks the problem solved. */
    public function step(Request $request, Problem $problem, int $step, Problems $problems, Tenant $tenant)
    {
        $this->authorize('solve', $problem);
        $rec = $problem->solve;
        abort_unless($rec && $rec->mode === 'full' && isset(self::STEPS[$step]), 422);
        abort_if($problem->status === 'solved' && $step < 6, 422);
        $rules = [
            1 => ['ignite' => 'nullable|string|max:500'],
            2 => ['statement' => 'required|string|min:10|max:280'],
            3 => ['observe' => 'required|string|max:2000'],
            4 => ['leverage' => 'nullable|string|max:2000'],
            5 => ['solution' => 'required|string|max:2000'],
            6 => ['evaluate_on' => 'required|date', 'evaluation' => 'nullable|string|max:2000', 'evaluation_result' => 'nullable|in:worked,partly,failed'],
        ][$step];
        $d = $request->validate($rules);
        if ($step === 5 && ! Task::where('source_type', 'problem')->where('source_id', $problem->id)->exists()) {
            throw ValidationException::withMessages(['solution' => 'Add at least one task with the full form before finishing this step.']);
        }
        if ($step === 6 && ($d['evaluation_result'] ?? null) && ! ($d['evaluation'] ?? null)) {
            throw ValidationException::withMessages(['evaluation' => 'Add a note on what happened.']);
        }
        $rec->fill($d);
        $rec->step = min(6, max($rec->step, $step + 1));
        if ($step === 5) {
            $rec->evaluate_on ??= Calendar::for($tenant->organization)->today()->addDays(14)->toDateString();
            $problems->markSolved($problem, $this->meetingFor($request, $problem));
        }
        $rec->save();

        return $this->back($request, $problem)->with('status', $step === 5 ? 'Solved. Set when to check that it worked' : 'Saved');
    }

    public function upgrade(Request $request, Problem $problem, Problems $problems)
    {
        $this->authorize('solve', $problem);
        abort_unless($problem->status === 'solving' && $problem->solve?->mode === 'quick', 422);
        $problems->start($problem, 'full');

        return $this->back($request, $problem)->with('status', 'Switched to full mode');
    }

    /** Evaluation for quick or full solves, from evaluate_on onward. */
    public function evaluate(Request $request, Problem $problem)
    {
        $this->authorize('solve', $problem);
        abort_unless($problem->status === 'solved' && $problem->solve, 422);
        $d = $request->validate(['evaluation_result' => 'required|in:worked,partly,failed', 'evaluation' => 'required|string|max:2000'], ['evaluation.required' => 'Add a note on what happened.']);
        $problem->solve->fill($d)->save();

        return $this->back($request, $problem)->with('status', 'Evaluation saved');
    }

    /** "Failed" offers raising it again with the old problem as the source. */
    public function raiseAgain(Problem $problem, Problems $problems)
    {
        $this->authorize('solve', $problem);
        abort_unless($problem->solve?->evaluation_result === 'failed', 422);
        $new = $problems->raise(['team_seat_id' => $problem->team_seat_id, 'title' => $problem->title, 'description' => $problem->description, 'horizon' => $problem->horizon, 'owner_id' => $problem->owner_id], 'problem', $problem->id);

        return redirect()->route('org.problems.show', $new)->with('status', 'Raised again');
    }
}
