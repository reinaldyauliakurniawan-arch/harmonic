<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticCycle;
use App\Models\Event;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Problems;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Company Diagnostic (PRD DG-01 to DG-04). */
class DiagnosticController extends Controller
{
    /** Section score = (average − 1) ÷ 4 × 100, rounded; overall = mean of the six section scores. */
    public static function results(DiagnosticCycle $c): array
    {
        $cfg = config('diagnostic');
        $answers = DB::table('diagnostic_answers')->where('cycle_id', $c->id)->get();
        $byQ = $answers->groupBy('question_key')->map(fn ($g) => $g->avg('score'));
        $sections = [];
        foreach ($cfg['sections'] as $key => $label) {
            $keys = array_keys(array_filter($cfg['statements'], fn ($s) => $s[0] === $key));
            $avg = $answers->whereIn('question_key', $keys)->avg('score');
            $sections[$key] = $avg === null ? null : (int) round(($avg - 1) / 4 * 100);
        }
        $vals = array_filter($sections, fn ($v) => $v !== null);
        $gaps = $byQ->sort()->take(3)->map(fn ($avg, $k) => ['key' => $k, 'text' => $cfg['statements'][$k][1], 'score' => (int) round(($avg - 1) / 4 * 100)])->values();

        return [
            'responses' => DB::table('diagnostic_completions')->where('cycle_id', $c->id)->count(),
            'sections' => $sections, 'overall' => $vals ? (int) round(array_sum($vals) / count($vals)) : null, 'gaps' => $gaps,
        ];
    }

    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $open = DiagnosticCycle::whereNull('closed_at')->first();
        $closed = DiagnosticCycle::whereNotNull('closed_at')->orderByDesc('closed_at')->get();
        $shown = $request->query('cycle') ? $closed->firstWhere('id', (int) $request->query('cycle')) : $closed->first();

        return view('diagnostic.index', [
            'open' => $open, 'closed' => $closed, 'shown' => $shown, 'results' => $shown ? self::results($shown) : null,
            'answered' => $open && DB::table('diagnostic_completions')->where('cycle_id', $open->id)->where('member_id', $access->id())->exists(),
            'openCount' => $open ? DB::table('diagnostic_completions')->where('cycle_id', $open->id)->count() : 0,
            'canLead' => auth()->user()->can('lead', $tenant->organization), 'isLeadership' => $access->leadership(),
            'trend' => $closed->reverse()->values()->map(fn ($c) => ['label' => $c->closed_at->format('j M Y'), 'r' => self::results($c)]),
            'cal' => Calendar::for($tenant->organization), 'cfg' => config('diagnostic'),
        ]);
    }

    public function open(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('lead', $tenant->organization);
        $days = (int) $request->validate(['days' => 'required|integer|between:7,30'])['days'];
        if (DiagnosticCycle::whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['days' => 'A cycle is already open.']);
        }
        DB::transaction(function () use ($days, $access, $tenant) {
            $c = new DiagnosticCycle;
            $c->opened_by = $access->id();
            $c->opened_at = now();
            $c->closes_at = now()->addDays($days);
            $c->save();
            $cal = Calendar::for($tenant->organization);
            $e = new Event(['title' => 'Company Diagnostic', 'type' => 'diagnostic', 'scope' => 'org', 'all_day' => true,
                'starts_at' => $cal->today()->utc(), 'ends_at' => CarbonImmutable::instance($cal->local($c->closes_at))->endOfDay()->utc()]);
            $e->created_by = $access->id();
            $e->save();
        });

        return back()->with('status', 'Cycle opened. Everyone sees it on Home');
    }

    public function answer(Request $request, DiagnosticCycle $cycle, Access $access)
    {
        $this->authorize('view', app(Tenant::class)->organization);
        abort_if($cycle->closed_at, 422);
        $keys = array_keys(config('diagnostic.statements'));
        $d = $request->validate(collect($keys)->mapWithKeys(fn ($k) => ["answers.$k" => 'required|integer|between:1,5'])->all(), ['answers.*.required' => 'Pick an answer.']);
        $me = $access->id();
        // Completion and answers are written to separate tables in one transaction (D15).
        DB::transaction(function () use ($cycle, $me, $keys, $d) {
            if (DB::table('diagnostic_completions')->where('cycle_id', $cycle->id)->where('member_id', $me)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['answers' => 'You already answered this cycle.']);
            }
            DB::table('diagnostic_completions')->insert(['cycle_id' => $cycle->id, 'member_id' => $me, 'completed_at' => now()]);
            $rows = array_map(fn ($k) => ['id' => (string) Str::uuid(), 'cycle_id' => $cycle->id, 'question_key' => $k, 'score' => (int) $d['answers'][$k]], $keys);
            shuffle($rows);
            DB::table('diagnostic_answers')->insert($rows);
        });

        return back()->with('status', 'Answer sent. Results appear when the cycle closes');
    }

    /** DG-03: leadership raises a section or statement as a long-term problem in the Leadership Team's queue. */
    public function raise(Request $request, DiagnosticCycle $cycle, Access $access, Problems $problems, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        abort_unless($cycle->closed_at, 422);
        $key = $request->validate(['key' => 'required|string|max:20'])['key'];
        $cfg = config('diagnostic');
        $title = $cfg['statements'][$key][1] ?? (isset($cfg['sections'][$key]) ? $cfg['sections'][$key].' scored low in the Company Diagnostic' : null);
        abort_unless($title, 404);
        $team = $access->formation()->leadershipSeatId();
        abort_unless($access->formation()->isTeam($team), 422);
        $p = $problems->raise(['team_seat_id' => $team, 'title' => Str::limit($title, 155), 'horizon' => 'long'], 'diagnostic', $cycle->id);

        return redirect()->route('org.problems.show', $p)->with('status', 'Added to the Leadership Team\'s long-term queue');
    }
}
