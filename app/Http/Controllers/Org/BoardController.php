<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** RACI+ board view (PRD T-06). */
class BoardController extends Controller
{
    public function index(Request $request, Access $access)
    {
        $this->authorize('create', Board::class);
        $archived = $request->boolean('archived');
        $boards = Board::when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->withCount(['tasks' => fn ($q) => $q->whereIn('status', ['todo', 'doing'])])->orderBy('title')->paginate(25);

        return view('boards.index', ['boards' => $boards, 'archived' => $archived, 'teams' => $access->formation()->teamOptions()]);
    }

    public function store(Request $request, Access $access)
    {
        $this->authorize('create', Board::class);
        $data = $request->validate(['title' => 'required|string|min:2|max:120', 'team_seat_id' => 'nullable|integer']);
        if (($data['team_seat_id'] ?? null) && ! $access->formation()->isTeam((int) $data['team_seat_id'])) {
            throw ValidationException::withMessages(['team_seat_id' => 'Choose a team.']);
        }
        $board = DB::transaction(function () use ($data, $access) {
            $board = new Board($data);
            $board->created_by = $access->id();
            $board->save();
            foreach (['To do', 'Doing', 'Done'] as $i => $t) {
                $board->columns()->create(['title' => $t, 'sort_order' => $i, 'is_done_column' => $t === 'Done']);
            }

            return $board;
        });

        return redirect()->route('org.boards.show', $board)->with('status', 'Board created');
    }

    public function show(Board $board, Tenant $tenant, Access $access)
    {
        $this->authorize('view', $board);
        $board->load(['columns']);
        $tasks = Task::where('board_id', $board->id)->whereIn('status', ['todo', 'doing', 'done'])
            ->with(['responsible.user', 'steps'])->orderBy('position')->get()->groupBy('board_column_id');

        return view('boards.show', [
            'board' => $board, 'tasks' => $tasks, 'canEdit' => auth()->user()->can('update', $board),
            'members' => Tasks::memberOptions(), 'today' => Calendar::for($tenant->organization)->today(),
            'teams' => $access->formation()->teamOptions(),
        ]);
    }

    public function archive(Board $board)
    {
        $this->authorize('update', $board);
        $board->archived_at = $board->archived_at ? null : now();
        $board->save();

        return redirect()->route('org.boards.index')->with('status', $board->archived_at ? 'Board archived' : 'Board restored');
    }

    public function addColumn(Request $request, Board $board)
    {
        $this->authorize('update', $board);
        $title = $request->validate(['title' => 'required|string|min:1|max:60'])['title'];
        if ($board->columns()->count() >= 8) {
            throw ValidationException::withMessages(['title' => 'A board has at most 8 columns.']);
        }
        $done = $board->columns()->where('is_done_column', true)->first();
        // New columns go before the done column so it stays last.
        $board->columns()->where('sort_order', '>=', $done->sort_order)->increment('sort_order');
        $board->columns()->create(['title' => $title, 'sort_order' => $done->sort_order]);

        return back()->with('status', 'Column added');
    }

    public function updateColumn(Request $request, Board $board, BoardColumn $column)
    {
        $this->authorize('update', $board);
        abort_unless($column->board_id === $board->id, 404);
        $data = $request->validate(['title' => 'nullable|string|min:1|max:60', 'move' => 'nullable|in:left,right', 'done' => 'nullable|boolean']);
        DB::transaction(function () use ($board, $column, $data) {
            if (! empty($data['title'])) {
                $column->title = $data['title'];
                $column->save();
            }
            if (! empty($data['move'])) {
                $cols = $board->columns()->get()->values();
                $i = $cols->search(fn ($c) => $c->id === $column->id);
                $j = $data['move'] === 'left' ? $i - 1 : $i + 1;
                if (isset($cols[$j])) {
                    [$a, $b] = [$cols[$i]->sort_order, $cols[$j]->sort_order];
                    $cols[$i]->sort_order = $b;
                    $cols[$j]->sort_order = $a;
                    $cols[$i]->save();
                    $cols[$j]->save();
                }
            }
            if (! empty($data['done'])) {
                $board->columns()->update(['is_done_column' => false]);
                $column->is_done_column = true;
                $column->save();
            }
            $this->resyncStatuses($board);
        });

        return back();
    }

    public function destroyColumn(Board $board, BoardColumn $column)
    {
        $this->authorize('update', $board);
        abort_unless($column->board_id === $board->id, 404);
        if (Task::where('board_column_id', $column->id)->exists()) {
            throw ValidationException::withMessages(['column' => 'Move its cards first. Only an empty column can be deleted.']);
        }
        if ($column->is_done_column) {
            throw ValidationException::withMessages(['column' => 'Choose another done column first.']);
        }
        if ($board->columns()->count() <= 2) {
            throw ValidationException::withMessages(['column' => 'A board needs at least 2 columns.']);
        }
        $column->delete();
        $this->resyncStatuses($board);

        return back()->with('status', 'Column deleted');
    }

    /** Column order or the done column changed: every card's status follows its column again. */
    private function resyncStatuses(Board $board): void
    {
        $cols = $board->columns()->get();
        $tasks = app(Tasks::class);
        foreach (Task::where('board_id', $board->id)->whereIn('status', ['todo', 'doing', 'done'])->get() as $t) {
            $col = $cols->firstWhere('id', $t->board_column_id);
            if ($col && ($s = $tasks->statusForColumn($cols, $col, $t->status)) !== $t->status) {
                $tasks->setStatus($t, $s);
            }
        }
    }

    /** Drag and drop and the "Move to…" menu both post here. */
    public function moveCard(Request $request, Board $board, Task $task, Tasks $tasks)
    {
        $this->authorize('status', $task);
        abort_unless($task->board_id === $board->id, 404);
        $column = $board->columns()->findOrFail($request->validate(['column_id' => 'required|integer'])['column_id']);
        $tasks->moveToColumn($task, $column);
        if ($request->expectsJson()) {
            return response()->json(['status' => $task->status]);
        }

        return back()->with('status', 'Moved to '.$column->title);
    }
}
