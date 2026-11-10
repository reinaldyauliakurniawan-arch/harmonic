<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\MissionMilestone;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quarterly Breakthrough Missions (PRD MI-01 to MI-04). */
class MissionController extends Controller
{
    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $cal = Calendar::for($tenant->organization);
        $q = $request->validate(['qi' => 'nullable|integer|between:-200,200', 'level' => 'nullable|in:company,team,personal', 'team' => 'nullable|integer', 'owner' => 'nullable|integer', 'view' => 'nullable|in:list,cascade']);
        $current = $cal->quarterIndex($cal->today());
        $qi = $q['qi'] ?? $current;
        $qs = $cal->quarterStart($qi)->toDateString();
        $missions = Mission::with(['owner.user', 'milestones'])->where('quarter_start', $qs)
            ->when($q['level'] ?? null, fn ($w, $l) => $w->where('level', $l))
            ->when($q['team'] ?? null, fn ($w, $t) => $w->where('team_seat_id', $t))
            ->when($q['owner'] ?? null, fn ($w, $o) => $w->where('owner_id', $o))
            ->orderByRaw("field(level, 'company', 'team', 'personal')")->orderBy('id')->get();

        return view('missions.index', [
            'missions' => $missions, 'q' => $q, 'qi' => $qi, 'current' => $current, 'cal' => $cal, 'view' => $q['view'] ?? 'list',
            'teams' => $access->formation()->teamOptions(), 'members' => Tasks::memberOptions(),
        ]);
    }

    public function create(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $cal = Calendar::for($tenant->organization);

        return view('missions.form', ['mission' => null, 'cal' => $cal, 'teams' => $access->formation()->teamOptions(), 'members' => Tasks::memberOptions(),
            'parents' => Mission::whereIn('level', ['company', 'team'])->whereIn('status', ['on_track', 'off_track'])->where('quarter_start', '>=', $cal->currentQuarterStart()->toDateString())->get(),
            'preset' => $request->only(['level', 'team_seat_id', 'owner_id'])]);
    }

    private function quarterOptions(Calendar $cal): array
    {
        $now = $cal->quarterIndex($cal->today());

        return [$cal->quarterStart($now)->toDateString() => $cal->quarterLabel($cal->quarterStart($now)).' (current)', $cal->quarterStart($now + 1)->toDateString() => $cal->quarterLabel($cal->quarterStart($now + 1)).' (next)'];
    }

    public function store(Request $request, Access $access, Tenant $tenant)
    {
        $cal = Calendar::for($tenant->organization);
        $d = $request->validate([
            'level' => 'required|in:company,team,personal', 'team_seat_id' => 'nullable|required_if:level,team|integer',
            'owner_id' => 'required|integer', 'title' => 'required|string|min:5|max:160', 'done_definition' => 'required|string|min:10|max:500',
            'quarter_start' => 'required|in:'.implode(',', array_keys($this->quarterOptions($cal))), 'parent_id' => 'nullable|integer',
        ]);
        $team = $d['level'] === 'team' ? (int) $d['team_seat_id'] : null;
        $this->authorize('create', [Mission::class, $d['level'], $team, (int) $d['owner_id']]);
        $f = $access->formation();
        abort_unless(Membership::active()->whereKey($d['owner_id'])->exists(), 422);
        if ($team && ! in_array((int) $d['owner_id'], $f->teamMemberIds($team), true)) {
            throw ValidationException::withMessages(['owner_id' => 'The owner must be a member of that team.']);
        }
        $parent = null;
        if ($d['parent_id'] ?? null) {
            $parent = Mission::findOrFail($d['parent_id']);
            $ok = ($d['level'] === 'team' && $parent->level === 'company') || ($d['level'] === 'personal' && in_array($parent->level, ['company', 'team'], true));
            if (! $ok) {
                throw ValidationException::withMessages(['parent_id' => 'Team Missions link to company Missions; personal Missions link to team or company Missions.']);
            }
        }
        $m = new Mission(['level' => $d['level'], 'team_seat_id' => $team, 'parent_id' => $parent?->id, 'title' => $d['title'], 'done_definition' => $d['done_definition'], 'quarter_start' => $d['quarter_start']]);
        $m->owner_id = (int) $d['owner_id'];
        $m->created_by = $access->id();
        $m->status = 'on_track';
        $m->status_updated_at = now();
        $m->save();

        return redirect()->route('org.missions.show', $m)->with('status', 'Mission created');
    }

    public function show(Mission $mission, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $mission);
        $mission->load(['owner.user', 'milestones', 'parent', 'children.owner.user']);
        $cal = Calendar::for($tenant->organization);
        $tasks = Task::where(fn ($q) => $q->where('source_type', 'mission')->where('source_id', $mission->id)
            ->orWhere(fn ($q) => $q->where('source_type', 'milestone')->whereIn('source_id', $mission->milestones->pluck('id'))))->with('responsible.user')->get();
        $end = $cal->quarterEndFor($mission->quarter_start);

        return view('missions.show', [
            'mission' => $mission, 'cal' => $cal, 'tasks' => $tasks, 'end' => $end, 'ended' => $end->lt($cal->today()),
            'teams' => $access->formation()->teamOptions(), 'members' => Tasks::memberOptions(),
            'carried' => $mission->carried_from_id ? Mission::withTrashed()->find($mission->carried_from_id) : null,
        ]);
    }

    public function update(Request $request, Mission $mission)
    {
        $this->authorize('update', $mission);
        $d = $request->validate(['title' => 'required|string|min:5|max:160', 'done_definition' => 'required|string|min:10|max:500']);
        $mission->fill($d)->save();

        return back()->with('status', 'Saved');
    }

    public function status(Request $request, Mission $mission)
    {
        $this->authorize('status', $mission);
        abort_unless($mission->isOpen(), 422);
        $mission->status = $request->validate(['status' => 'required|in:on_track,off_track'])['status'];
        $mission->status_updated_at = now();
        $mission->save();

        return back()->with('status', $mission->status === 'on_track' ? 'Marked on track' : 'Marked off track');
    }

    /** MI-03: Done, Not done, or carry into next quarter. Never automatic. */
    public function close(Request $request, Mission $mission, Tenant $tenant)
    {
        $this->authorize('close', $mission);
        abort_unless($mission->isOpen(), 422);
        $result = $request->validate(['result' => 'required|in:done,not_done,carry'])['result'];
        $cal = Calendar::for($tenant->organization);
        $copy = DB::transaction(function () use ($mission, $result, $cal) {
            $mission->status = $result === 'done' ? 'done' : 'not_done';
            $mission->closed_at = now();
            $mission->save();
            if ($result !== 'carry') {
                return null;
            }
            $copy = $mission->replicate(['status', 'closed_at', 'carried_from_id', 'status_updated_at']);
            $copy->quarter_start = $cal->quarterStart($cal->quarterIndex($mission->quarter_start) + 1)->toDateString();
            $copy->status = 'on_track';
            $copy->status_updated_at = now();
            $copy->carried_from_id = $mission->id;
            $copy->save();
            foreach ($mission->milestones()->whereNull('done_at')->get() as $ms) {
                $copy->milestones()->create(['title' => $ms->title, 'sort_order' => $ms->sort_order]);
            }

            return $copy;
        });

        return $copy ? redirect()->route('org.missions.show', $copy)->with('status', 'Carried into next quarter') : back()->with('status', 'Closed');
    }

    public function destroy(Mission $mission)
    {
        $this->authorize('delete', $mission);
        $mission->delete();

        return redirect()->route('org.missions.index')->with('status', 'Mission deleted');
    }

    public function addMilestone(Request $request, Mission $mission, Tenant $tenant)
    {
        $this->authorize('update', $mission);
        $cal = Calendar::for($tenant->organization);
        $end = $cal->quarterEndFor($mission->quarter_start)->toDateString();
        $d = $request->validate(['title' => 'required|string|min:2|max:160', 'due_date' => 'nullable|date|after_or_equal:'.$mission->quarter_start->toDateString().'|before_or_equal:'.$end],
            ['due_date.after_or_equal' => 'Pick a date inside the Mission\'s quarter.', 'due_date.before_or_equal' => 'Pick a date inside the Mission\'s quarter.']);
        if ($mission->milestones()->count() >= 10) {
            throw ValidationException::withMessages(['title' => 'A Mission has at most 10 milestones.']);
        }
        $mission->milestones()->create($d + ['sort_order' => (int) $mission->milestones()->max('sort_order') + 1]);

        return back()->with('status', 'Milestone added');
    }

    public function toggleMilestone(Mission $mission, MissionMilestone $milestone)
    {
        $this->authorize('update', $mission);
        abort_unless($milestone->mission_id === $mission->id, 404);
        $milestone->done_at = $milestone->done_at ? null : now();
        $milestone->save();

        return back();
    }

    public function destroyMilestone(Mission $mission, MissionMilestone $milestone)
    {
        $this->authorize('update', $mission);
        abort_unless($milestone->mission_id === $mission->id, 404);
        $milestone->delete();

        return back()->with('status', 'Milestone removed');
    }
}
