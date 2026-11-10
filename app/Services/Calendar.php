<?php

namespace App\Services;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Week and quarter math for one organization. A quarter is 13 weeks; quarters are counted
 * from the configured quarter start date, snapped back to the organization's week start day.
 */
class Calendar
{
    public function __construct(
        public readonly string $timezone,
        public readonly int $weekStartDay,
        public readonly CarbonImmutable $anchor,
        private ?CarbonImmutable $now = null,
    ) {}

    public static function for(Organization $org, ?CarbonInterface $now = null): self
    {
        $tz = $org->timezone ?: 'Asia/Jakarta';
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone($tz);
        $base = $org->quarter_start_date
            ? CarbonImmutable::parse($org->quarter_start_date->format('Y-m-d'), $tz)
            : $now->firstOfQuarter();
        $cal = new self($tz, (int) ($org->week_start_day ?: 1), $base, $now);

        return new self($tz, $cal->weekStartDay, $cal->weekStart($base), $now);
    }

    public function now(): CarbonImmutable
    {
        return $this->now ?? CarbonImmutable::now($this->timezone);
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function weekStart(CarbonInterface $date): CarbonImmutable
    {
        $d = CarbonImmutable::parse($date->format('Y-m-d'), $this->timezone);
        $back = ($d->dayOfWeekIso - $this->weekStartDay + 7) % 7;

        return $d->subDays($back);
    }

    public function currentWeekStart(): CarbonImmutable
    {
        return $this->weekStart($this->today());
    }

    public function quarterIndex(CarbonInterface $date): int
    {
        $days = $this->anchor->diffInDays(CarbonImmutable::parse($date->format('Y-m-d'), $this->timezone), false);

        return (int) floor($days / 91);
    }

    public function quarterStart(int $index): CarbonImmutable
    {
        return $this->anchor->addDays(91 * $index);
    }

    public function quarterEnd(int $index): CarbonImmutable
    {
        return $this->quarterStart($index)->addDays(90);
    }

    public function quarterStartFor(CarbonInterface $date): CarbonImmutable
    {
        return $this->quarterStart($this->quarterIndex($date));
    }

    public function currentQuarterStart(): CarbonImmutable
    {
        return $this->quarterStartFor($this->today());
    }

    public function quarterEndFor(CarbonInterface $quarterStart): CarbonImmutable
    {
        return $this->quarterEnd($this->quarterIndex($quarterStart));
    }

    /** @return CarbonImmutable[] the 13 week starts of the quarter containing $date */
    public function weeksOfQuarter(CarbonInterface $date): array
    {
        $start = $this->quarterStartFor($date);

        return array_map(fn ($i) => $start->addWeeks($i), range(0, 12));
    }

    /** @return CarbonImmutable[] the 13 week starts ending with the current week */
    public function trailingWeeks(int $count = 13): array
    {
        $current = $this->currentWeekStart();

        return array_map(fn ($i) => $current->subWeeks($count - 1 - $i), range(0, $count - 1));
    }

    public function weekNumber(CarbonInterface $date): int
    {
        $start = $this->quarterStartFor($date);

        return intdiv((int) $start->diffInDays(CarbonImmutable::parse($date->format('Y-m-d'), $this->timezone)), 7) + 1;
    }

    public function quarterLabel(CarbonInterface $date): string
    {
        $index = $this->quarterIndex($date);
        $q = (($index % 4) + 4) % 4 + 1;

        return 'Q'.$q.' '.$this->quarterStart($index)->format('Y');
    }

    public function local(?CarbonInterface $utc): ?CarbonImmutable
    {
        return $utc ? CarbonImmutable::instance($utc)->setTimezone($this->timezone) : null;
    }

    /** Parse a local date-time typed by a user into UTC for storage. */
    public function toUtc(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, $this->timezone)->utc();
    }

    public static function formatDate(?CarbonInterface $d): string
    {
        return $d ? $d->format('D, j M Y') : '';
    }
}
