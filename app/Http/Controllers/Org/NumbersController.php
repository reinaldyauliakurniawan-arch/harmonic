<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Services\Access;
use App\Services\Metrics;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The Numbers: my metrics and one form for this week (PRD N-01). */
class NumbersController extends Controller
{
    public function index(Access $access, Metrics $metrics, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $seats = $access->formation()->seatIdsHeldBy($access->id());
        $list = Metric::whereIn('owner_seat_id', $seats ?: [0])->whereNull('archived_at')->with('teamSeat')->orderBy('team_seat_id')->orderBy('sort_order')->get();
        $week = $metrics->cal()->currentWeekStart();
        $values = MetricValue::whereIn('metric_id', $list->pluck('id'))->where('week_start', $week->toDateString())->get()->keyBy('metric_id');
        $last = MetricValue::whereIn('metric_id', $list->pluck('id'))->where('week_start', $week->subWeek()->toDateString())->get()->keyBy('metric_id');

        return view('numbers.index', ['list' => $list, 'week' => $week, 'values' => $values, 'last' => $last, 'cal' => $metrics->cal(), 'currency' => $tenant->organization->currency]);
    }

    public function store(Request $request, Access $access, Metrics $metrics)
    {
        $this->authorize('view', app(Tenant::class)->organization);
        $d = $request->validate(['values' => 'array', 'values.*' => 'nullable|string|max:40']);
        $week = $metrics->cal()->currentWeekStart()->toDateString();
        $seats = $access->formation()->seatIdsHeldBy($access->id());
        $list = Metric::whereIn('owner_seat_id', $seats ?: [0])->whereNull('archived_at')->get()->keyBy('id');
        $errors = [];
        DB::transaction(function () use ($d, $list, $metrics, $week, &$errors) {
            foreach ($d['values'] ?? [] as $id => $raw) {
                if (! isset($list[$id])) {
                    continue;
                }
                $existing = MetricValue::where('metric_id', $id)->where('week_start', $week)->exists();
                if (trim((string) $raw) === '' && ! $existing) {
                    continue;
                }
                try {
                    $metrics->save($list[$id], $week, $raw);
                } catch (ValidationException $e) {
                    $errors['values.'.$id] = $e->validator->errors()->first();
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });

        return back()->with('status', 'Saved');
    }
}
