<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Metric;
use App\Models\Mission;
use App\Models\Problem;
use App\Models\Procedure;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Problems;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The Problem Queue (PRD P-01 to P-04). */
class ProblemController extends Controller
{
    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('create', Problem::class);
        $q = $request->validate(['team' => 'nullable|integer', 'tab' => 'nullable|in:short,long,solved,dropped']);
        $f = $access->formation();
        $teams = $f->teamOptions();
        $team = (int) $this->pickTeam($q['team'] ?? null, $teams, $f->teamIdsOf($access->id())[0] ?? array_key_first($teams));
        $tab = $q['tab'] ?? 'short';
        $problems = Problem::with('owner.user')->where('team_seat_id', $team)
            ->when(in_array($tab, ['short', 'long'], true), fn ($w) => $w->whereIn('status', ['open', 'solving'])->where('horizon', $tab)->orderByRaw('`rank` is null')->orderBy('rank')->orderBy('created_at'),
                fn ($w) => $w->where('status', $tab)->latest('updated_at'))
            ->paginate(25)->withQueryString();

        return view('problems.index', ['problems' => $problems, 'teams' => $teams, 'team' => $team, 'tab' => $tab,
            'canMaintain' => $access->inTeam($team), 'myTeam' => $f->teamIdsOf($access->id())[0] ?? $team]);
    }

    public function create(Request $request, Access $access)
    {
        $this->authorize('create', Problem::class);
        $f = $access->formation();
        $team = (int) ($request->query('team') ?: $this->pickTeam(null, $f->teamOptions(), $f->teamIdsOf($access->id())[0] ?? array_key_first($f->teamOptions())));

        return view('problems.form', ['teams' => $f->teamOptions(), 'team' => $team, 'members' => app(Problems::class)->teamOwnerOptions($team),
            'preset' => $request->only(['title', 'source_type', 'source_id', 'horizon', 'description', 'back'])]);
    }

    public function store(Request $request, Problems $problems)
    {
        $this->authorize('create', Problem::class);
        $d = $request->validate([
            'team_seat_id' => 'required|integer', 'title' => 'required|string|min:2|max:160', 'description' => 'nullable|string|max:2000',
            'horizon' => 'required|in:short,long', 'owner_id' => 'nullable|integer',
            'source_type' => 'nullable|in:'.implode(',', array_keys(Problems::SOURCES)), 'source_id' => 'nullable|integer|required_with:source_type',
        ]);
        $this->checkSource($d['source_type'] ?? null, $d['source_id'] ?? null);
        $p = $problems->raise($d, $d['source_type'] ?? null, $d['source_id'] ?? null);
        $back = $request->input('back');

        return ($back && str_starts_with($back, url('/')) ? redirect($back) : redirect()->route('org.problems.show', $p))->with('status', 'Problem raised');
    }

    private function checkSource(?string $type, ?int $id): void
    {
        if (! $type) {
            return;
        }
        $exists = match ($type) {
            'metric' => Metric::whereKey($id)->exists(),
            'mission' => Mission::whereKey($id)->exists(),
            'problem' => Problem::whereKey($id)->exists(),
            'headline' => DB::table('meeting_headlines')->where('id', $id)->whereIn('meeting_id', Meeting::select('id'))->exists(),
            'audit' => DB::table('sync_audits')->where('id', $id)->whereIn('procedure_id', Procedure::select('id'))->exists(),
            'alignment', 'diagnostic' => true,
        };
        if (! $exists) {
            throw ValidationException::withMessages(['source_id' => 'That item no longer exists.']);
        }
    }

    public function show(Problem $problem, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $problem);
        $problem->load(['owner.user', 'raiser.user', 'solve']);
        $f = $access->formation();

        return view('problems.show', [
            'p' => $problem, 'teams' => $f->teamOptions(), 'members' => app(Problems::class)->teamOwnerOptions($problem->team_seat_id),
            'tasks' => Task::where('source_type', 'problem')->where('source_id', $problem->id)->with('responsible.user')->get(),
            'duplicates' => Problem::where('team_seat_id', $problem->team_seat_id)->whereIn('status', ['open', 'solving'])->where('id', '!=', $problem->id)->pluck('title', 'id'),
            'dupOf' => $problem->duplicate_of_id ? Problem::withTrashed()->find($problem->duplicate_of_id) : null,
            'source' => $problem->source_type === 'problem' ? Problem::withTrashed()->find($problem->source_id) : ($problem->source_type === 'metric' ? Metric::find($problem->source_id) : ($problem->source_type === 'mission' ? Mission::find($problem->source_id) : null)),
            'canMaintain' => auth()->user()->can('maintain', $problem), 'canSolve' => auth()->user()->can('solve', $problem),
            'today' => Calendar::for($tenant->organization)->today(), 'meeting' => request()->query('meeting'),
            'procedures' => Procedure::whereNull('archived_at')->whereNotNull('published_at')->orderBy('title')->pluck('title', 'id'),
        ]);
    }

    public function maintain(Request $request, Problem $problem, Problems $problems, Access $access)
    {
        $this->authorize('maintain', $problem);
        abort_unless($problem->isOpen(), 422);
        $d = $request->validate([
            'action' => 'required|in:horizon,move,drop,duplicate,rank,owner',
            'horizon' => 'required_if:action,horizon|in:short,long', 'team_seat_id' => 'required_if:action,move|integer',
            'reason' => 'required_if:action,drop|nullable|string|max:280', 'duplicate_of' => 'required_if:action,duplicate|integer',
            'rank' => 'nullable|integer|between:1,3', 'owner_id' => 'nullable|integer',
        ], ['reason.required_if' => 'Say why you are dropping it.']);
        switch ($d['action']) {
            case 'horizon':
                $problem->horizon = $d['horizon'];
                $problem->rank = null;
                $problem->save();
                break;
            case 'move':
                abort_unless($access->formation()->isTeam((int) $d['team_seat_id']), 422);
                $problem->team_seat_id = (int) $d['team_seat_id'];
                $problem->rank = null;
                $problem->owner_id = in_array($problem->owner_id, $access->formation()->teamMemberIds($problem->team_seat_id), true) ? $problem->owner_id : null;
                $problem->save();
                ActivityLog::record('problem.moved', $problem, ['title' => $problem->title, 'team' => $problem->team_seat_id]);
                break;
            case 'drop':
                $problems->drop($problem, $d['reason']);
                break;
            case 'duplicate':
                $of = Problem::whereIn('status', ['open', 'solving'])->where('id', '!=', $problem->id)->findOrFail($d['duplicate_of']);
                $problems->drop($problem, mb_substr('Duplicate of '.$of->title, 0, 280), $of->id);
                break;
            case 'rank':
                $problems->rank($problem, $d['rank'] ?? null);
                break;
            case 'owner':
                $owner = $d['owner_id'] ?? null;
                if ($owner && ! in_array((int) $owner, $access->formation()->teamMemberIds($problem->team_seat_id), true)) {
                    throw ValidationException::withMessages(['owner_id' => 'The owner must be a member of the team.']);
                }
                $problem->owner_id = $owner ? (int) $owner : null;
                $problem->save();
                break;
        }

        return back()->with('status', 'Saved');
    }
}
