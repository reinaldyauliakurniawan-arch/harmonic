<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Stored occurrences for repeating events, kept 26 weeks ahead (PRD C-03). */
class Recurrence
{
    public const AHEAD_WEEKS = 26;

    public function __construct(private Tenant $tenant) {}

    private function cal(): Calendar
    {
        return Calendar::for($this->tenant->organization);
    }

    /** Start of occurrence n (0-based) after $first, in the organization's timezone. */
    public function nth(string $rule, CarbonImmutable $first, int $n): CarbonImmutable
    {
        $cal = $this->cal();
        $local = $first->setTimezone($cal->timezone);
        $at = match ($rule) {
            'weekly' => $local->addWeeks($n),
            'biweekly' => $local->addWeeks(2 * $n),
            'monthly' => $this->monthly($local, $n),
            'quarterly' => $this->quarterly($cal, $local, $n),
        };

        return $at->utc();
    }

    private function monthly(CarbonImmutable $local, int $n): CarbonImmutable
    {
        $month = $local->startOfMonth()->addMonthsNoOverflow($n);
        $day = min($local->day, $month->daysInMonth);

        return $month->setDate($month->year, $month->month, $day)->setTimeFrom($local);
    }

    private function quarterly(Calendar $cal, CarbonImmutable $local, int $n): CarbonImmutable
    {
        $offset = $local->startOfDay()->diffInDays($cal->quarterStartFor($local), true);
        $q = $cal->quarterStart($cal->quarterIndex($local) + $n);

        return $q->addDays((int) $offset)->setTimeFrom($local);
    }

    /** Creates the series and its occurrences from a first event's attributes. */
    public function create(Event $template, string $rule, ?string $until): EventSeries
    {
        return DB::transaction(function () use ($template, $rule, $until) {
            $series = EventSeries::create(['rule' => $rule, 'until' => $until]);
            $template->series_id = $series->id;
            $template->save();
            $this->fill($series, $template);

            return $series;
        });
    }

    /** Adds missing occurrences up to 26 weeks ahead, copying the latest stored occurrence. */
    public function fill(EventSeries $series, ?Event $template = null): void
    {
        $template ??= Event::withTrashed()->where('series_id', $series->id)->orderByDesc('starts_at')->first();
        if (! $template) {
            return;
        }
        // Count from the first occurrence so monthly dates clamped to a short month don't drift.
        $first = CarbonImmutable::parse(Event::withTrashed()->where('series_id', $series->id)->min('starts_at') ?? $template->starts_at, 'UTC');
        $last = CarbonImmutable::instance($template->starts_at);
        $duration = $template->starts_at->diffInSeconds($template->ends_at);
        $horizon = CarbonImmutable::now()->addWeeks(self::AHEAD_WEEKS);
        $until = $series->until ? CarbonImmutable::parse($series->until->format('Y-m-d').' 23:59:59', $this->cal()->timezone)->utc() : null;
        for ($n = 1; $n < 400; $n++) {
            $start = $this->nth($series->rule, $first, $n);
            if ($start->lte($last)) {
                continue;
            }
            if ($start->gt($horizon) || ($until && $start->gt($until))) {
                break;
            }
            $copy = $template->replicate(['notes']);
            $copy->deleted_at = null;
            $copy->starts_at = $start;
            $copy->ends_at = $start->addSeconds((int) $duration);
            $copy->save();
        }
    }

    public function extendAll(): void
    {
        EventSeries::where(fn ($q) => $q->whereNull('until')->orWhere('until', '>=', now()->toDateString()))
            ->get()->each(fn ($s) => $this->fill($s));
    }

    /** "This and following": moves $event and every later occurrence to a new series and ends the old one before it. */
    public function split(Event $event): EventSeries
    {
        $old = EventSeries::findOrFail($event->series_id);
        $new = EventSeries::create(['rule' => $old->rule, 'until' => $old->until]);
        Event::where('series_id', $old->id)->where('starts_at', '>=', $event->starts_at)->update(['series_id' => $new->id]);
        $old->until = $this->cal()->local($event->starts_at)->subDay()->toDateString();
        $old->save();

        return $new;
    }
}
