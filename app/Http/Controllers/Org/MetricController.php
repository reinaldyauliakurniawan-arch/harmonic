<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Services\Access;
use App\Services\Metrics;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Metrics Dashboard (PRD M-01 to M-07). */
class MetricController extends Controller
{
    public function index(Request $request, Access $access, Metrics $metrics, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $q = $request->validate(['team' => 'nullable|integer', 'qi' => 'nullable|integer|between:-200,200', 'trailing' => 'nullable|boolean', 'archived' => 'nullable|boolean']);
        $f = $access->formation();
        $teams = $f->teamOptions();
        $team = (int) $this->pickTeam($q['team'] ?? null, $teams, $f->teamIdsOf($access->id())[0] ?? array_key_first($teams));
        $cal = $metrics->cal();
        $currentQi = $cal->quarterIndex($cal->today());
        $qi = $q['qi'] ?? $currentQi;
        $trailing = (bool) ($q['trailing'] ?? false);
        $weeks = $trailing ? $cal->trailingWeeks() : $cal->weeksOfQuarter($cal->quarterStart($qi));
        $list = Metric::where('team_seat_id', $team)
            ->when($q['archived'] ?? false, fn ($w) => $w->whereNotNull('archived_at'), fn ($w) => $w->whereNull('archived_at'))
            ->orderBy('sort_order')->orderBy('id')->get();
        $rollupWeeks = $trailing ? $cal->weeksOfQuarter($cal->today()) : $weeks;
        $from = min($weeks[0], $rollupWeeks[0])->toDateString();
        $to = max(end($weeks), end($rollupWeeks))->toDateString();
        $values = MetricValue::whereIn('metric_id', $list->pluck('id'))->whereBetween('week_start', [$from, $to])->get()
            ->groupBy('metric_id')->map(fn ($g) => $g->keyBy(fn ($v) => $v->week_start->toDateString()));
        $order = array_flip($f->ordered()->pluck('id')->all());
        $groups = $list->groupBy('owner_seat_id')->sortBy(fn ($g, $seat) => $order[$seat] ?? PHP_INT_MAX);

        return view('metrics.index', [
            'teams' => $teams, 'team' => $team, 'groups' => $groups, 'weeks' => $weeks, 'values' => $values, 'rollupWeeks' => $rollupWeeks,
            'metrics' => $metrics, 'cal' => $cal, 'f' => $f, 'qi' => $qi, 'currentQi' => $currentQi, 'trailing' => $trailing,
            'archived' => (bool) ($q['archived'] ?? false), 'canCreate' => auth()->user()->can('create', [Metric::class, $team]),
            'currency' => $tenant->organization->currency, 'count' => $list->count(),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:120', 'description' => 'nullable|string|max:500',
            'owner_seat_id' => 'required|integer', 'unit' => 'required|in:number,percent,currency,hours',
            'decimals' => 'required|integer|between:0,2', 'operator' => 'required|in:gte,lte,eq,gt,lt,between',
            'goal_value' => 'required|numeric|between:-99999999999,99999999999', 'goal_value_max' => 'nullable|required_if:operator,between|numeric|gt:goal_value',
            'quarter_rollup' => 'required|in:sum,average,latest', 'auto_problem_after' => 'nullable|integer|between:1,4',
        ];
    }

    private function checkOwnerSeat(Access $access, int $team, int $seat): void
    {
        if (! in_array($seat, $access->formation()->teamSeatIds($team), true)) {
            throw ValidationException::withMessages(['owner_seat_id' => 'Choose the team\'s head seat or one of its seats.']);
        }
    }

    public function create(Request $request, Access $access)
    {
        $team = (int) $request->query('team');
        $this->authorize('create', [Metric::class, $team]);

        return view('metrics.create', ['metric' => null, 'team' => $team, 'f' => $access->formation(), 'activeCount' => Metric::where('team_seat_id', $team)->whereNull('archived_at')->count()]);
    }

    public function store(Request $request, Access $access)
    {
        $team = (int) $request->input('team_seat_id');
        $this->authorize('create', [Metric::class, $team]);
        $data = $request->validate($this->rules());
        $this->checkOwnerSeat($access, $team, (int) $data['owner_seat_id']);
        $m = new Metric($data + ['team_seat_id' => $team]);
        $m->goal_value_max = $data['operator'] === 'between' ? $data['goal_value_max'] : null;
        $m->sort_order = (int) Metric::where('team_seat_id', $team)->max('sort_order') + 1;
        $m->save();

        return redirect()->route('org.metrics.index', ['team' => $team])->with('status', 'Metric added');
    }

    public function show(Metric $metric, Metrics $metrics, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $metric);
        $cal = $metrics->cal();
        $weeks = $cal->trailingWeeks(52);
        $vals = MetricValue::where('metric_id', $metric->id)->where('week_start', '>=', $weeks[0]->toDateString())->get()->keyBy(fn ($v) => $v->week_start->toDateString());
        $notes = MetricValue::where('metric_id', $metric->id)->whereNotNull('note')->orderByDesc('week_start')->limit(50)->get();
        $metric->load('ownerSeat.holder.user');

        return view('metrics.show', [
            'metric' => $metric, 'cal' => $cal, 'weeks' => $weeks, 'vals' => $vals, 'notes' => $notes, 'metrics' => $metrics,
            'f' => $access->formation(), 'canEdit' => auth()->user()->can('update', $metric), 'canEnter' => auth()->user()->can('enter', $metric),
            'currency' => $tenant->organization->currency, 'activeCount' => 0,
        ]);
    }

    public function update(Request $request, Metric $metric, Access $access, Metrics $metrics)
    {
        $this->authorize('update', $metric);
        $data = $request->validate($this->rules() + ['reapply' => 'boolean']);
        $this->checkOwnerSeat($access, $metric->team_seat_id, (int) $data['owner_seat_id']);
        $metric->fill($data);
        $metric->goal_value_max = $data['operator'] === 'between' ? $data['goal_value_max'] : null;
        $metric->save();
        if ($data['reapply'] ?? false) {
            $metrics->reapplyGoal($metric);
        }

        return back()->with('status', 'Saved');
    }

    public function archive(Metric $metric)
    {
        $this->authorize('update', $metric);
        $metric->archived_at = $metric->archived_at ? null : now();
        $metric->save();

        return redirect()->route('org.metrics.index', ['team' => $metric->team_seat_id])->with('status', $metric->archived_at ? 'Archived' : 'Restored');
    }

    /** M-02: new order of one owner seat's metrics, from drag and drop or the arrow buttons. */
    public function order(Request $request)
    {
        $ids = array_map('intval', $request->validate(['ids' => 'required|array|max:100', 'ids.*' => 'integer'])['ids']);
        $list = Metric::whereIn('id', $ids)->get();
        abort_unless($list->count() === count($ids) && $list->pluck('team_seat_id')->unique()->count() === 1, 422);
        $this->authorize('update', $list->first());
        foreach ($ids as $i => $id) {
            Metric::whereKey($id)->update(['sort_order' => $i]);
        }

        return response()->json(['saved' => true]);
    }

    /** Inline cell save (M-03): JSON for the grid, redirect for plain forms. */
    public function value(Request $request, Metric $metric, Metrics $metrics, Tenant $tenant)
    {
        $this->authorize('enter', $metric);
        $d = $request->validate(['week_start' => 'required|date_format:Y-m-d', 'value' => 'nullable|string|max:40', 'note' => 'nullable|string|max:280', 'note_only' => 'boolean']);
        $row = $metrics->save($metric, $d['week_start'], $d['value'] ?? null, $request->has('note') ? ($d['note'] ?? '') : null, (bool) ($d['note_only'] ?? false));
        if ($request->expectsJson()) {
            $week = CarbonImmutable::parse($d['week_start']);

            return response()->json([
                'raw' => $row ? rtrim(rtrim(number_format($row->value, $metric->decimals, '.', ''), '0'), '.') : '',
                'status' => $metrics->cellStatus($row, $week, $metric),
                'note' => $row?->note,
                'html' => view('metrics.cell', ['v' => $row, 'metric' => $metric, 'status' => $metrics->cellStatus($row, $week, $metric), 'currency' => $tenant->organization->currency, 'editable' => true])->render(),
            ]);
        }

        return back()->with('status', 'Saved');
    }
}
