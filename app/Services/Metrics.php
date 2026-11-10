<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Models\Problem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Metrics Dashboard rules (PRD section 12). */
class Metrics
{
    public const OPERATORS = ['gte' => '≥', 'lte' => '≤', 'eq' => '=', 'gt' => '>', 'lt' => '<', 'between' => '–'];

    public function __construct(private Access $access, private Tenant $tenant) {}

    /** Both sides are rounded to the metric's decimals before comparing. */
    public static function evaluate(float $value, string $op, float $goal, ?float $max, int $decimals): string
    {
        $v = round($value, $decimals);
        $g = round($goal, $decimals);
        $m = $max === null ? null : round($max, $decimals);
        $on = match ($op) {
            'gte' => $v >= $g,
            'lte' => $v <= $g,
            'eq' => abs($v - $g) < 1e-9,
            'gt' => $v > $g,
            'lt' => $v < $g,
            'between' => $v >= $g && $v <= $m,
        };

        return $on ? 'on' : 'off';
    }

    public static function format(?float $value, string $unit, int $decimals, string $currency = 'IDR'): string
    {
        if ($value === null) {
            return '';
        }
        $n = number_format($value, $decimals, '.', ',');

        return match ($unit) {
            'percent' => $n.'%',
            'currency' => $currency.' '.$n,
            'hours' => $n.' h',
            default => $n,
        };
    }

    public static function goalLabel(string $op, float $goal, ?float $max, string $unit, int $decimals, string $currency = 'IDR'): string
    {
        $f = fn ($v) => self::format($v, $unit, $decimals, $currency);

        return $op === 'between' ? $f($goal).'–'.$f($max) : self::OPERATORS[$op].' '.$f($goal);
    }

    /** Accepts digits, one decimal point, and a minus sign; strips thousand separators and %. */
    public static function parse(string $raw): ?float
    {
        $s = str_replace([',', '%', ' ', "\u{00A0}"], '', trim($raw));
        if ($s === '') {
            return null;
        }
        if (! preg_match('/^-?\d*\.?\d+$|^-?\d+\.$/', $s)) {
            throw ValidationException::withMessages(['value' => 'Enter a number, like 1,500 or 12.5.']);
        }

        return (float) $s;
    }

    public function cal(): Calendar
    {
        return Calendar::for($this->tenant->organization);
    }

    /** Owner may enter the current and previous quarter; the team leader any week; future weeks never (M-03). */
    public function canEnter(Metric $metric, CarbonInterface $week): bool
    {
        $cal = $this->cal();
        if ($week->gt($cal->currentWeekStart())) {
            return false;
        }
        if ($this->access->leads($metric->team_seat_id)) {
            return true;
        }
        $holder = $this->access->formation()->seat($metric->owner_seat_id)?->holder_id;
        if (! $holder || $holder !== $this->access->id()) {
            return false;
        }

        return $cal->quarterIndex($week) >= $cal->quarterIndex($cal->today()) - 1;
    }

    /** Saves or clears one week. Returns the saved row, or null when cleared. */
    public function save(Metric $metric, string $weekStart, ?string $raw, ?string $note = null, bool $noteOnly = false): ?MetricValue
    {
        $cal = $this->cal();
        $week = $cal->weekStart(CarbonImmutable::parse($weekStart, $cal->timezone));
        if ($week->toDateString() !== $weekStart) {
            throw ValidationException::withMessages(['week_start' => 'That date is not the start of a week.']);
        }
        if (! $this->canEnter($metric, $week)) {
            abort(403);
        }

        return DB::transaction(function () use ($metric, $week, $raw, $note, $noteOnly) {
            $row = MetricValue::where('metric_id', $metric->id)->where('week_start', $week->toDateString())->lockForUpdate()->first();
            if ($noteOnly) {
                if (! $row) {
                    throw ValidationException::withMessages(['note' => 'Enter a value for this week before adding a note.']);
                }
                $row->note = $note ?: null;
                $row->save();

                return $row;
            }
            $value = self::parse((string) $raw);
            if ($value === null) {
                $row?->delete();

                return null;
            }
            $row ??= new MetricValue(['week_start' => $week->toDateString()]);
            $row->metric_id = $metric->id;
            $row->value = $value;
            $this->snapshot($row, $metric);
            $row->entered_by = $this->access->id();
            if ($note !== null) {
                $row->note = $note ?: null;
            }
            $row->save();
            $this->autoProblem($metric);

            return $row;
        });
    }

    /** Copies the goal in force onto the value row (M-03). */
    public function snapshot(MetricValue $row, Metric $metric): void
    {
        $row->operator = $metric->operator;
        $row->goal_value = $metric->goal_value;
        $row->goal_value_max = $metric->operator === 'between' ? $metric->goal_value_max : null;
        $row->status = self::evaluate((float) $row->value, $metric->operator, (float) $metric->goal_value, $row->goal_value_max, (int) $metric->decimals);
    }

    /** M-04: re-snapshot this quarter's saved weeks with the metric's current goal. */
    public function reapplyGoal(Metric $metric): int
    {
        $cal = $this->cal();
        $start = $cal->currentQuarterStart();
        $rows = MetricValue::where('metric_id', $metric->id)->whereBetween('week_start', [$start->toDateString(), $cal->currentQuarterStart()->addDays(90)->toDateString()])->get();
        foreach ($rows as $row) {
            $this->snapshot($row, $metric);
            $row->save();
        }
        ActivityLog::record('metric.goal_reapplied', $metric, ['title' => $metric->name, 'weeks' => $rows->count()]);

        return $rows->count();
    }

    /** M-05: N consecutive saved weeks off and no open problem from this metric creates one. */
    public function autoProblem(Metric $metric): ?Problem
    {
        $n = $metric->auto_problem_after;
        if (! $n) {
            return null;
        }
        $last = MetricValue::where('metric_id', $metric->id)->orderByDesc('week_start')->limit($n)->pluck('status');
        if ($last->count() < $n || $last->contains(fn ($s) => $s !== 'off')) {
            return null;
        }
        $exists = Problem::where('team_seat_id', $metric->team_seat_id)->where('source_type', 'metric')->where('source_id', $metric->id)
            ->whereIn('status', ['open', 'solving'])->exists();
        if ($exists) {
            return null;
        }
        $p = new Problem(['team_seat_id' => $metric->team_seat_id, 'title' => mb_substr($metric->name.' off-track for '.$n.' '.($n === 1 ? 'week' : 'weeks'), 0, 160), 'horizon' => 'short']);
        $p->owner_id = $this->access->formation()->seat($metric->owner_seat_id)?->holder_id;
        $p->raised_by = $this->access->id();
        $p->status = 'open';
        $p->source_type = 'metric';
        $p->source_id = $metric->id;
        $p->save();

        return $p;
    }

    public function rollup(Metric $metric, $values): ?float
    {
        $vals = collect($values)->filter(fn ($v) => $v !== null && $v->value !== null)->sortBy('week_start');
        if ($vals->isEmpty()) {
            return null;
        }

        return match ($metric->quarter_rollup) {
            'sum' => $vals->sum('value'),
            'average' => $vals->avg('value'),
            'latest' => $vals->last()->value,
        };
    }

    /** Status of a cell: on, off, missing (week ended without a value), or null (not due yet, or before the metric existed). */
    public function cellStatus(?MetricValue $v, CarbonInterface $week, ?Metric $metric = null): ?string
    {
        if ($v) {
            return $v->status;
        }
        $cal = $this->cal();
        if ($metric?->created_at && $week->lt($cal->weekStart($cal->local($metric->created_at)))) {
            return null;
        }

        return $week->copy()->addDays(7)->lte($cal->today()) ? 'missing' : null;
    }
}
