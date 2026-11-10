<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Belief;
use App\Models\Evaluation;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Personnel Evaluator (PRD E-01 to E-06). */
class EvaluatorController extends Controller
{
    public const LABELS = ['plus' => 'Lives it', 'mixed' => 'Sometimes', 'minus' => 'Rarely'];

    public static function result(array $scores, bool $u, bool $w, bool $c, Organization $org): array
    {
        $counts = array_count_values($scores);
        $person = ($counts['minus'] ?? 0) <= $org->evaluator_bar_max_minus && ($counts['mixed'] ?? 0) <= $org->evaluator_bar_max_mixed;
        $seat = $u && $w && $c;

        return ['person' => $person, 'seat' => $seat, 'label' => ($person ? 'Right person' : 'Wrong person').', '.($seat ? 'right seat' : 'wrong seat')];
    }

    public static function bar(Organization $org): string
    {
        $minus = $org->evaluator_bar_max_minus ? 'at most '.$org->evaluator_bar_max_minus.' Rarely' : 'no Rarely';

        return 'The bar: '.$minus.', at most '.$org->evaluator_bar_max_mixed.' Sometimes.';
    }

    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $f = $access->formation();
        $me = $access->id();
        $seatIds = $f->subtreeSeatIds($me);
        $team = $request->query('team');
        if ($team) {
            $seatIds = array_values(array_intersect($seatIds, $f->teamSeatIds((int) $team)));
        }
        $rows = collect($seatIds)->map(fn ($id) => $f->seat($id))->filter(fn ($s) => $s->holder_id && $s->holder_id !== $me)->values();
        $latest = Evaluation::with('scores')->whereIn('seat_id', $rows->pluck('id'))->orderByDesc('evaluated_on')->orderByDesc('id')->get()
            ->unique(fn ($e) => $e->subject_id.':'.$e->seat_id)->keyBy(fn ($e) => $e->subject_id.':'.$e->seat_id);

        return view('evaluator.index', [
            'rows' => $rows, 'latest' => $latest, 'beliefs' => Belief::whereNull('archived_at')->orderBy('sort_order')->get(),
            'bar' => self::bar($tenant->organization), 'org' => $tenant->organization, 'teams' => $f->teamOptions(), 'team' => $team, 'me' => $me,
        ]);
    }

    public function create(Request $request, Access $access, Tenant $tenant)
    {
        $seat = $access->formation()->seat((int) $request->query('seat'));
        abort_unless($seat && $seat->holder_id, 404);
        $this->authorize('create', [Evaluation::class, $seat->id]);

        return view('evaluator.form', ['e' => null, 'seat' => $seat, 'subject' => Membership::with('user')->find($seat->holder_id),
            'beliefs' => Belief::whereNull('archived_at')->orderBy('sort_order')->get(), 'org' => $tenant->organization, 'bar' => self::bar($tenant->organization)]);
    }

    private function validated(Request $request, $beliefs): array
    {
        $rules = ['understands' => 'required|boolean', 'wants' => 'required|boolean', 'capacity' => 'required|boolean', 'notes' => 'nullable|string|max:2000'];
        foreach ($beliefs as $b) {
            $rules['scores.'.$b->id] = 'required|in:plus,mixed,minus';
        }

        return $request->validate($rules, ['scores.*.required' => 'Rate every belief.', 'understands.required' => 'Answer yes or no.', 'wants.required' => 'Answer yes or no.', 'capacity.required' => 'Answer yes or no.']);
    }

    public function store(Request $request, Access $access, Tenant $tenant)
    {
        $seat = $access->formation()->seat((int) $request->input('seat_id'));
        abort_unless($seat && $seat->holder_id, 404);
        $this->authorize('create', [Evaluation::class, $seat->id]);
        $beliefs = Belief::whereNull('archived_at')->get();
        if ($beliefs->isEmpty()) {
            throw ValidationException::withMessages(['scores' => 'Add beliefs to the Fundamentals first.']);
        }
        $d = $this->validated($request, $beliefs);
        $e = DB::transaction(function () use ($d, $seat, $access, $tenant) {
            $e = new Evaluation(['understands' => $d['understands'], 'wants' => $d['wants'], 'capacity' => $d['capacity'], 'notes' => $d['notes'] ?? null]);
            $e->subject_id = $seat->holder_id;
            $e->seat_id = $seat->id;
            $e->evaluator_id = $access->id();
            $e->evaluated_on = Calendar::for($tenant->organization)->today()->toDateString();
            $e->save();
            foreach ($d['scores'] as $belief => $score) {
                DB::table('evaluation_scores')->insert(['evaluation_id' => $e->id, 'belief_id' => (int) $belief, 'score' => $score]);
            }

            return $e;
        });

        return redirect()->route('org.evaluator.person', ['membership' => $e->subject_id])->with('status', 'Evaluation saved');
    }

    public function edit(Evaluation $evaluation, Access $access, Tenant $tenant)
    {
        $this->authorize('update', $evaluation);
        $seat = $access->formation()->seat($evaluation->seat_id);

        return view('evaluator.form', ['e' => $evaluation->load('scores'), 'seat' => $seat, 'subject' => Membership::with('user')->find($evaluation->subject_id),
            'beliefs' => Belief::whereNull('archived_at')->orderBy('sort_order')->get(), 'org' => $tenant->organization, 'bar' => self::bar($tenant->organization)]);
    }

    public function update(Request $request, Evaluation $evaluation)
    {
        $this->authorize('update', $evaluation);
        $beliefs = Belief::whereNull('archived_at')->get();
        $d = $this->validated($request, $beliefs);
        DB::transaction(function () use ($evaluation, $d) {
            $evaluation->fill(['understands' => $d['understands'], 'wants' => $d['wants'], 'capacity' => $d['capacity'], 'notes' => $d['notes'] ?? null])->save();
            DB::table('evaluation_scores')->where('evaluation_id', $evaluation->id)->delete();
            foreach ($d['scores'] as $belief => $score) {
                DB::table('evaluation_scores')->insert(['evaluation_id' => $evaluation->id, 'belief_id' => (int) $belief, 'score' => $score]);
            }
        });

        return redirect()->route('org.evaluator.person', ['membership' => $evaluation->subject_id])->with('status', 'Saved');
    }

    public function person(Membership $membership, Access $access, Tenant $tenant)
    {
        $this->authorize('viewPerson', [Evaluation::class, $membership->id]);
        $evals = Evaluation::with(['scores', 'evaluator.user', 'seat'])->where('subject_id', $membership->id)->orderByDesc('evaluated_on')->orderByDesc('id')->get();

        return view('evaluator.person', ['subject' => $membership->load('user'), 'evals' => $evals, 'org' => $tenant->organization,
            'beliefs' => Belief::orderBy('sort_order')->get()->keyBy('id'), 'bar' => self::bar($tenant->organization), 'me' => $access->id()]);
    }
}
