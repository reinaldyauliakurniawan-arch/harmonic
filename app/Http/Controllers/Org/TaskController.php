<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Task;
use App\Models\TaskStep;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('create', Task::class);
        $cal = Calendar::for($tenant->organization);
        $f = $access->formation();
        $q = $request->validate([
            'tab' => 'nullable|in:mine,team,all', 'status' => 'nullable|in:open,todo,doing,done,cancelled',
            'due' => 'nullable|in:overdue,this_week,next_week,later', 'person' => 'nullable|integer', 'team' => 'nullable|integer',
            'source' => 'nullable|in:none,'.implode(',', array_keys(Tasks::SOURCES)),
        ]);
        $tab = $q['tab'] ?? 'mine';
        $me = $access->id();
        $team = $tab === 'team' ? $this->pickTeam($q['team'] ?? null, $f->teamOptions(), $f->teamIdsOf($me)[0] ?? null) : ($q['team'] ?? null);
        $week = $cal->currentWeekStart();
        $today = $cal->today()->toDateString();

        $tasks = Task::with(['responsible.user', 'accountable.user', 'steps'])
            ->when($tab === 'mine', fn ($w) => $w->where(fn ($w) => $w->where('responsible_id', $me)->orWhere('accountable_id', $me)))
            ->when($team, fn ($w) => $w->where('team_seat_id', $team))
            ->when(($q['status'] ?? 'open') === 'open', fn ($w) => $w->whereIn('status', ['todo', 'doing']), fn ($w) => $w->where('status', $q['status']))
            ->when($q['due'] ?? null, fn ($w, $d) => match ($d) {
                'overdue' => $w->where('due_date', '<', $today)->whereIn('status', ['todo', 'doing']),
                'this_week' => $w->whereBetween('due_date', [$week->toDateString(), $week->addDays(6)->toDateString()]),
                'next_week' => $w->whereBetween('due_date', [$week->addWeek()->toDateString(), $week->addDays(13)->toDateString()]),
                'later' => $w->where('due_date', '>', $week->addDays(13)->toDateString()),
            })
            ->when($q['person'] ?? null, fn ($w, $p) => $w->where(fn ($w) => $w->where('responsible_id', $p)->orWhere('accountable_id', $p)))
            ->when($q['source'] ?? null, fn ($w, $s) => $s === 'none' ? $w->whereNull('source_type') : $w->where('source_type', $s))
            ->orderBy('due_date')->orderBy('created_at')->paginate(25)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks, 'tab' => $tab, 'q' => $q, 'team' => $team, 'today' => $cal->today(),
            'members' => Tasks::memberOptions(), 'teams' => $f->teamOptions(), 'myTeams' => $f->teamIdsOf($me),
            'defaultDue' => $cal->today()->addDays(7)->toDateString(),
        ]);
    }

    public function store(Request $request, Tasks $tasks)
    {
        $this->authorize('create', Task::class);
        self::stepsFromText($request);
        $full = $request->boolean('full') || $request->has('steps');
        $strict = $request->input('source_type') === 'problem' && $request->boolean('strict');
        $data = $request->validate($full ? $tasks->fullRules($strict) : $tasks->quickRules());
        $task = $tasks->create($data, $strict);

        return back()->with('status', 'Task created')->with('created_task', $task->id);
    }

    /** Steps are typed one per line. */
    public static function stepsFromText(Request $request): void
    {
        if ($request->has('steps_text')) {
            $request->merge(['steps' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $request->input('steps_text'))), 'strlen'))]);
        }
    }

    public function show(Task $task, Access $access, Tasks $tasks, Tenant $tenant)
    {
        $this->authorize('view', $task);
        $task->load(['responsible.user', 'accountable.user', 'creator.user', 'steps', 'people.user', 'board.columns']);

        return view('tasks.show', [
            'task' => $task, 'source' => $tasks->sourceLink($task), 'members' => Tasks::memberOptions(),
            'teams' => $access->formation()->teamOptions(),
            'boards' => Board::whereNull('archived_at')->with('columns')->orderBy('title')->get(),
            'canEdit' => $access->member() && auth()->user()->can('update', $task),
            'canStatus' => auth()->user()->can('status', $task),
            'canDelete' => auth()->user()->can('delete', $task),
            'today' => Calendar::for($tenant->organization)->today(),
        ]);
    }

    public function update(Request $request, Task $task, Tasks $tasks)
    {
        $this->authorize('update', $task);
        self::stepsFromText($request);
        $tasks->update($task, $request->validate($tasks->fullRules()));

        return back()->with('status', 'Saved');
    }

    public function status(Request $request, Task $task, Tasks $tasks)
    {
        $this->authorize('status', $task);
        $status = $request->validate(['status' => 'required|in:todo,doing,done,cancelled'])['status'];
        $open = $task->steps()->whereNull('done_at')->count();
        if ($status === 'done' && $open && ! $request->boolean('confirmed')) {
            return back()->with('confirm_done', [$task->id, $open]);
        }
        $tasks->setStatus($task, $status);

        return back()->with('status', ['done' => 'Marked done', 'todo' => 'Reopened', 'doing' => 'Marked doing', 'cancelled' => 'Cancelled'][$status]);
    }

    public function step(Task $task, TaskStep $step)
    {
        $this->authorize('update', $task);
        abort_unless($step->task_id === $task->id, 404);
        $step->done_at = $step->done_at ? null : now();
        $step->save();

        return back();
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('org.tasks.index')->with('status', 'Task deleted');
    }
}
