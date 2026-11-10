<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\AlignmentRound;
use App\Models\Membership;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Fundamentals;
use App\Services\Problems;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Alignment Check (PRD A-01 to A-06). Answers are anonymous at the data level (D15). */
class AlignmentController extends Controller
{
    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $rounds = AlignmentRound::orderByDesc('opened_at')->get();
        $round = $request->query('round') ? $rounds->firstWhere('id', (int) $request->query('round')) : $rounds->first();
        $me = $access->id();
        $answered = $round ? DB::table('alignment_completions')->where('round_id', $round->id)->where('member_id', $me)->exists() : false;
        $results = $round ? self::results($round) : null;
        $completion = null;
        if ($round && $access->leadership()) {
            $done = DB::table('alignment_completions')->where('round_id', $round->id)->pluck('member_id')->all();
            $members = Membership::active()->with('user')->get()->sortBy('name');
            $completion = ['done' => $members->whereIn('id', $done), 'waiting' => $members->whereNotIn('id', $done)];
        }
        $trend = $rounds->reverse()->values()->map(fn ($r) => ['label' => $r->opened_at->format('j M Y'), 'res' => self::results($r)]);

        return view('alignment.index', [
            'round' => $round, 'rounds' => $rounds, 'answered' => $answered, 'results' => $results, 'completion' => $completion,
            'canLead' => $access->leadership(), 'hasPublished' => (bool) app(Fundamentals::class)->latest(), 'trend' => $trend,
            'cal' => Calendar::for($tenant->organization),
        ]);
    }

    public static function results(AlignmentRound $round): array
    {
        $answers = DB::table('alignment_answers')->where('round_id', $round->id)->get();
        $responses = DB::table('alignment_completions')->where('round_id', $round->id)->count();
        $clear = [];
        foreach (array_keys(Fundamentals::PARTS) as $k) {
            $rows = $answers->where('part_key', $k);
            $clear[$k] = $rows->count() ? round($rows->where('clear', true)->count() / $rows->count() * 100) : null;
        }
        $committed = $answers->whereNotNull('committed');
        $comments = $answers->filter(fn ($a) => trim((string) $a->comment) !== '')->map(fn ($a) => ['id' => $a->id, 'part' => $a->part_key, 'text' => $a->comment])->shuffle()->values();

        return [
            'responses' => $responses, 'members' => $round->member_count, 'clear' => $clear,
            'avg_clear' => $responses ? round(collect($clear)->filter(fn ($v) => $v !== null)->avg()) : null,
            'committed' => $committed->count() ? round($committed->where('committed', true)->count() / $committed->count() * 100) : null,
            'comments' => $comments,
        ];
    }

    public function open(Request $request, Fundamentals $svc, Tenant $tenant, Access $access)
    {
        $this->authorize('leadership', $tenant->organization);
        $days = (int) $request->validate(['days' => 'required|integer|between:3,30'])['days'];
        $latest = $svc->latest();
        if (! $latest) {
            throw ValidationException::withMessages(['days' => 'Publish the Fundamentals first.']);
        }
        if (AlignmentRound::whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['days' => 'A round is already open.']);
        }
        $r = new AlignmentRound;
        $r->fundamentals_id = $latest->id;
        $r->opened_by = $access->id();
        $r->opened_at = now();
        $r->closes_at = now()->addDays($days);
        $r->member_count = Membership::active()->count();
        $r->save();

        return back()->with('status', 'Round opened. Everyone sees it on Home');
    }

    public function answer(Request $request, AlignmentRound $round, Access $access)
    {
        $this->authorize('view', app(Tenant::class)->organization);
        abort_unless($round->isOpen(), 422);
        $parts = array_keys(Fundamentals::PARTS);
        $rules = ['committed' => 'required|boolean'];
        foreach ($parts as $k) {
            $rules["clear.$k"] = 'required|boolean';
            $rules["comment.$k"] = 'nullable|string|max:1000';
        }
        $d = $request->validate($rules, ['clear.*.required' => 'Answer yes or no.', 'committed.required' => 'Answer yes or no.']);
        $me = $access->id();
        // Completion and answers go to separate tables in one transaction; answers never reference the member (D15).
        DB::transaction(function () use ($round, $me, $parts, $d) {
            if (DB::table('alignment_completions')->where('round_id', $round->id)->where('member_id', $me)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['committed' => 'You already answered this round.']);
            }
            DB::table('alignment_completions')->insert(['round_id' => $round->id, 'member_id' => $me, 'completed_at' => now()]);
            $rows = [];
            foreach ($parts as $i => $k) {
                $rows[] = ['id' => (string) Str::uuid(), 'round_id' => $round->id, 'part_key' => $k, 'clear' => (bool) $d['clear'][$k],
                    'committed' => $i === 0 ? (bool) $d['committed'] : null, 'comment' => $d['comment'][$k] ?? null];
            }
            shuffle($rows);
            DB::table('alignment_answers')->insert($rows);
        });

        return back()->with('status', 'Answer sent. Thank you');
    }

    public function close(AlignmentRound $round, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        abort_unless($round->isOpen(), 422);
        $round->closed_at = now();
        $round->save();

        return back()->with('status', 'Round closed');
    }

    /** A-04: one reminder per round. Without email it refreshes the Home banner wording for those who haven't answered. */
    public function remind(AlignmentRound $round, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        abort_unless($round->isOpen(), 422);
        if ($round->reminded_at) {
            throw ValidationException::withMessages(['remind' => 'You already sent the reminder for this round.']);
        }
        $round->reminded_at = now();
        $round->save();

        return back()->with('status', 'Reminder sent');
    }

    /** A-03: leadership turns a comment into a long-term problem for the Leadership Team. */
    public function commentToProblem(Request $request, AlignmentRound $round, Access $access, Problems $problems, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        $id = $request->validate(['answer' => 'required|uuid'])['answer'];
        $answer = DB::table('alignment_answers')->where('round_id', $round->id)->where('id', $id)->first();
        abort_unless($answer && trim((string) $answer->comment) !== '', 404);
        $team = $access->formation()->leadershipSeatId();
        abort_unless($access->formation()->isTeam($team), 422);
        $p = $problems->raise(['team_seat_id' => $team, 'title' => Str::limit($answer->comment, 155), 'description' => $answer->comment, 'horizon' => 'long'], 'alignment', $round->id);

        return redirect()->route('org.problems.show', $p)->with('status', 'Added to the Leadership Team\'s long-term queue');
    }
}
