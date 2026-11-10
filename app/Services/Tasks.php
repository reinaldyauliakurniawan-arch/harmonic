<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\MissionMilestone;
use App\Models\Problem;
use App\Models\Procedure;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** RACI+: the only task entity (PRD section 9). */
class Tasks
{
    /** source_type => [label, model] */
    public const SOURCES = [
        'problem' => ['Problem', Problem::class],
        'mission' => ['Mission', Mission::class],
        'milestone' => ['Milestone', MissionMilestone::class],
        'event' => ['Event', Event::class],
        'meeting' => ['Meeting', Meeting::class],
        'procedure' => ['Procedure', Procedure::class],
    ];

    public function __construct(private Access $access, private Tenant $tenant) {}

    public function quickRules(): array
    {
        $members = Rule::exists('memberships', 'id')->where('organization_id', $this->tenant->id())->where('status', 'active');

        return [
            'title' => 'required|string|min:2|max:160',
            'responsible_id' => ['required', 'integer', $members],
            'output' => 'required|string|min:2|max:280',
            'due_date' => 'required|date',
            'team_seat_id' => 'nullable|integer',
            'source_type' => ['nullable', Rule::in(array_keys(self::SOURCES))],
            'source_id' => 'nullable|integer|required_with:source_type',
        ];
    }

    public function fullRules(bool $strict = false): array
    {
        $members = Rule::exists('memberships', 'id')->where('organization_id', $this->tenant->id())->where('status', 'active');

        return $this->quickRules() + [
            'accountable_id' => [$strict ? 'required' : 'nullable', 'integer', $members],
            'consulted' => 'array', 'consulted.*' => ['integer', $members],
            'informed' => 'array', 'informed.*' => ['integer', $members],
            'steps' => [$strict ? 'required' : 'nullable', 'array', 'max:30'],
            'steps.*' => 'nullable|string|max:200',
            'definition_of_done' => [$strict ? 'required' : 'nullable', 'string', 'max:2000'],
            'board_id' => 'nullable|integer',
            'board_column_id' => 'nullable|integer',
        ];
    }

    /** Resolves a source item inside this organization, or fails validation. */
    public function source(?string $type, ?int $id): ?object
    {
        if (! $type) {
            return null;
        }
        $class = self::SOURCES[$type][1];
        $item = $class === MissionMilestone::class
            ? MissionMilestone::whereHas('mission')->find($id)
            : $class::find($id);
        if (! $item) {
            throw ValidationException::withMessages(['source_id' => 'That item no longer exists.']);
        }

        return $item;
    }

    public function create(array $data, bool $strict = false): Task
    {
        $f = $this->access->formation();
        $team = $data['team_seat_id'] ?? null;
        if ($team && ! $f->isTeam((int) $team)) {
            throw ValidationException::withMessages(['team_seat_id' => 'Choose a team.']);
        }
        $source = $this->source($data['source_type'] ?? null, $data['source_id'] ?? null);
        if ($source instanceof MissionMilestone) {
            $end = Calendar::for($this->tenant->organization)->quarterEndFor($source->mission->quarter_start);
            if ($data['due_date'] > $end->toDateString()) {
                throw ValidationException::withMessages(['due_date' => 'Due date can\'t be after the Mission\'s quarter ends on '.Calendar::formatDate($end).'.']);
            }
        }
        $steps = array_values(array_filter(array_map('trim', $data['steps'] ?? []), 'strlen'));
        if ($strict && ! $steps) {
            throw ValidationException::withMessages(['steps' => 'Add at least one step.']);
        }

        return DB::transaction(function () use ($data, $team, $steps) {
            $task = new Task([
                'title' => $data['title'], 'output' => $data['output'], 'due_date' => $data['due_date'],
                'definition_of_done' => $data['definition_of_done'] ?? null, 'team_seat_id' => $team,
            ]);
            $task->responsible_id = (int) $data['responsible_id'];
            $task->accountable_id = (int) ($data['accountable_id'] ?? null ?: $data['responsible_id']);
            $task->source_type = $data['source_type'] ?? null;
            $task->source_id = $data['source_id'] ?? null;
            $task->created_by = $this->access->id();
            $task->status = 'todo';
            $this->placeOnBoard($task, $data['board_id'] ?? null, $data['board_column_id'] ?? null);
            $task->save();
            $this->syncPeople($task, $data['consulted'] ?? [], $data['informed'] ?? []);
            foreach ($steps as $i => $s) {
                $task->steps()->create(['title' => $s, 'sort_order' => $i]);
            }

            return $task;
        });
    }

    public function update(Task $task, array $data): void
    {
        $f = $this->access->formation();
        if (($data['team_seat_id'] ?? null) && ! $f->isTeam((int) $data['team_seat_id'])) {
            throw ValidationException::withMessages(['team_seat_id' => 'Choose a team.']);
        }
        DB::transaction(function () use ($task, $data) {
            $task->fill(collect($data)->only(['title', 'output', 'due_date', 'definition_of_done', 'team_seat_id'])->all());
            $task->responsible_id = (int) $data['responsible_id'];
            $task->accountable_id = (int) ($data['accountable_id'] ?? null ?: $data['responsible_id']);
            if (array_key_exists('board_id', $data)) {
                $this->placeOnBoard($task, $data['board_id'] ?? null, $data['board_column_id'] ?? null);
            }
            $task->save();
            $this->syncPeople($task, $data['consulted'] ?? [], $data['informed'] ?? []);
            if (array_key_exists('steps', $data)) {
                $titles = array_values(array_filter(array_map('trim', $data['steps'] ?? []), 'strlen'));
                $existing = $task->steps()->get()->keyBy('title');
                $task->steps()->whereNotIn('title', $titles)->delete();
                foreach ($titles as $i => $t) {
                    $step = $existing[$t] ?? $task->steps()->make(['title' => $t]);
                    $step->sort_order = $i;
                    $step->save();
                }
            }
        });
    }

    private function syncPeople(Task $task, array $consulted, array $informed): void
    {
        $ra = [$task->responsible_id, $task->accountable_id];
        $rows = [];
        foreach (['consulted' => $consulted, 'informed' => $informed] as $role => $ids) {
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                if (in_array($id, $ra, true)) {
                    throw ValidationException::withMessages([$role => 'Consulted and Informed can\'t include the Responsible or Accountable person.']);
                }
                $rows[] = ['task_id' => $task->id, 'member_id' => $id, 'role' => $role];
            }
        }
        DB::table('task_people')->where('task_id', $task->id)->delete();
        DB::table('task_people')->insert($rows);
    }

    private function placeOnBoard(Task $task, $boardId, $columnId): void
    {
        if (! $boardId) {
            $task->board_id = null;
            $task->board_column_id = null;

            return;
        }
        $board = Board::whereNull('archived_at')->find($boardId);
        if (! $board) {
            throw ValidationException::withMessages(['board_id' => 'Choose a board.']);
        }
        $column = $columnId ? $board->columns()->find($columnId) : null;
        $column ??= $this->columnForStatus($board->columns()->get(), $task->status ?? 'todo');
        $task->board_id = $board->id;
        $task->board_column_id = $column->id;
        $task->status = $this->statusForColumn($board->columns()->get(), $column, $task->status ?? 'todo');
        $task->position = (int) Task::where('board_column_id', $column->id)->max('position') + 1;
        $this->stampCompletion($task);
    }

    /** Done column sets done, first column todo, any other doing (PRD T-06). */
    public function statusForColumn($columns, BoardColumn $column, string $current): string
    {
        if ($column->is_done_column) {
            return 'done';
        }

        return $columns->sortBy('sort_order')->first()->id === $column->id ? 'todo' : 'doing';
    }

    public function columnForStatus($columns, string $status): BoardColumn
    {
        $cols = $columns->sortBy('sort_order')->values();

        return match ($status) {
            'done' => $cols->firstWhere('is_done_column', true),
            'doing' => $cols->first(fn ($c, $i) => $i > 0 && ! $c->is_done_column) ?? $cols->first(),
            default => $cols->first(),
        };
    }

    public function setStatus(Task $task, string $status): void
    {
        $task->status = $status;
        $this->stampCompletion($task);
        if ($task->board_id && $status !== 'cancelled') {
            $columns = BoardColumn::where('board_id', $task->board_id)->get();
            $current = $columns->firstWhere('id', $task->board_column_id);
            if (! $current || $this->statusForColumn($columns, $current, $status) !== $status) {
                $task->board_column_id = $this->columnForStatus($columns, $status)->id;
            }
        }
        $task->save();
    }

    public function moveToColumn(Task $task, BoardColumn $column): void
    {
        $columns = BoardColumn::where('board_id', $column->board_id)->get();
        $task->board_id = $column->board_id;
        $task->board_column_id = $column->id;
        $task->status = $this->statusForColumn($columns, $column, $task->status);
        $task->position = (int) Task::where('board_column_id', $column->id)->max('position') + 1;
        $this->stampCompletion($task);
        $task->save();
    }

    private function stampCompletion(Task $task): void
    {
        if ($task->status === 'done') {
            $task->completed_at ??= now();
        } else {
            $task->completed_at = null;
        }
    }

    /** Share of a team's tasks due in a week that were done on or before their due date (PRD T-07). */
    public function completionRate(?int $teamSeatId, CarbonInterface $weekStart, ?array $memberIds = null): ?float
    {
        $q = Task::whereBetween('due_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->when($memberIds !== null, fn ($q) => $q->whereIn('responsible_id', $memberIds), fn ($q) => $q->where('team_seat_id', $teamSeatId));
        $total = (clone $q)->count();
        if (! $total) {
            return null;
        }
        $tz = $this->tenant->organization->timezone;
        $done = (clone $q)->where('status', 'done')->get(['due_date', 'completed_at'])
            ->filter(fn ($t) => $t->completed_at && $t->completed_at->setTimezone($tz)->toDateString() <= $t->due_date->toDateString())->count();

        return round($done / $total * 100, 1);
    }

    public function sourceLink(Task $task): ?array
    {
        if (! $task->source_type || ! isset(self::SOURCES[$task->source_type])) {
            return null;
        }
        $item = self::SOURCES[$task->source_type][1]::find($task->source_id);
        if (! $item) {
            return [self::SOURCES[$task->source_type][0], 'Removed item', null];
        }
        $title = $item->title ?? ($task->source_type === 'meeting' ? 'Weekly Meeting' : '');
        $url = match ($task->source_type) {
            'problem' => route('org.problems.show', $item),
            'mission' => route('org.missions.show', $item),
            'milestone' => route('org.missions.show', $item->mission_id),
            'event' => route('org.calendar.index', ['event' => $item->id, 'date' => $item->starts_at->toDateString()]),
            'meeting' => route('org.meetings.show', $item),
            'procedure' => route('org.procedures.show', $item),
            default => null,
        };

        return [self::SOURCES[$task->source_type][0], $title, $url];
    }

    public static function memberOptions(): array
    {
        return Membership::active()->with('user')->get()->sortBy('name')->mapWithKeys(fn ($m) => [$m->id => $m->name])->all();
    }
}
