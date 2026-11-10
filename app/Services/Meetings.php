<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\Problem;
use App\Models\Seat;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Meeting Cadence and the Weekly Meeting runner (PRD section 16). */
class Meetings
{
    public const SEGMENTS = ['Check-in', 'Metrics', 'Missions', 'Headlines', 'Tasks', 'Solve', 'Wrap-up'];

    private const DEFAULT_MINUTES = [5, 5, 5, 5, 5, 60, 5];

    public function __construct(private Access $access, private Tenant $tenant, private Recurrence $recurrence) {}

    /** Segment minutes scaled to the cadence length; Solve gets the remainder. */
    public static function minutes(int $length): array
    {
        $out = [];
        foreach (self::DEFAULT_MINUTES as $i => $m) {
            $out[$i] = $i === 5 ? 0 : max(1, (int) round($m * $length / 90));
        }
        $out[5] = $length - array_sum($out);

        return $out;
    }

    /** Saves the cadence and replaces the team's future, not yet started weekly meetings (ME-01). */
    public function setCadence(Seat $team, int $weekday, string $time, int $minutes): void
    {
        DB::transaction(function () use ($team, $weekday, $time, $minutes) {
            $team->meeting_weekday = $weekday;
            $team->meeting_time = $time;
            $team->meeting_minutes = $minutes;
            $team->save();

            $started = Meeting::whereNotNull('event_id')->whereNotNull('started_at')->pluck('event_id');
            Event::where('type', 'weekly_meeting')->where('team_seat_id', $team->id)->where('starts_at', '>', now())
                ->whereNotIn('id', $started)->delete();

            $cal = Calendar::for($this->tenant->organization);
            $first = $cal->now()->startOfDay();
            while ($first->dayOfWeekIso !== $weekday || CarbonImmutable::parse($first->toDateString().' '.$time, $cal->timezone)->lte($cal->now())) {
                $first = $first->addDay();
            }
            $start = CarbonImmutable::parse($first->toDateString().' '.$time, $cal->timezone);
            $event = new Event(['title' => $team->teamLabel().' Weekly Meeting', 'type' => 'weekly_meeting', 'scope' => 'team', 'team_seat_id' => $team->id,
                'starts_at' => $start->utc(), 'ends_at' => $start->addMinutes($minutes)->utc(), 'all_day' => false]);
            $event->created_by = $this->access->id();
            $event->save();
            $this->recurrence->create($event, 'weekly', null);
        });
    }

    public function start(Event $event): Meeting
    {
        return DB::transaction(function () use ($event) {
            $existing = Meeting::where('event_id', $event->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $m = new Meeting(['team_seat_id' => $event->team_seat_id, 'type' => 'weekly', 'event_id' => $event->id, 'scheduled_for' => $event->starts_at]);
            $m->started_at = now();
            $m->segment = 0;
            $m->segment_started_at = now();
            $m->facilitator_id = $this->access->id();
            $m->save();
            $rows = array_map(fn ($id) => ['meeting_id' => $m->id, 'member_id' => $id, 'present' => true], $this->access->formation()->teamMemberIds($m->team_seat_id));
            DB::table('meeting_attendance')->insert($rows);

            return $m;
        });
    }

    public function go(Meeting $m, int $segment): void
    {
        $m->segment = max(0, min(6, $segment));
        $m->segment_started_at = now();
        $m->save();
    }

    public function end(Meeting $m): void
    {
        DB::transaction(function () use ($m) {
            $m->ended_at = now();
            $m->save();
            app(Problems::class)->clearRanks($m->team_seat_id);
        });
    }

    /** Tasks created during the meeting by its team or from it. */
    public function tasksCreated(Meeting $m)
    {
        $end = $m->ended_at ?? now();

        return Task::where(fn ($q) => $q->where('source_type', 'meeting')->where('source_id', $m->id)
            ->orWhere(fn ($q) => $q->where('team_seat_id', $m->team_seat_id)->whereBetween('created_at', [$m->started_at, $end])))
            ->with('responsible.user')->get();
    }

    public function recap(Meeting $m): array
    {
        $att = DB::table('meeting_attendance')->where('meeting_id', $m->id)->get();
        $avg = DB::table('meeting_ratings')->where('meeting_id', $m->id)->avg('score');

        return [
            'present' => $att->where('present', true)->pluck('member_id')->all(),
            'absent' => $att->where('present', false)->pluck('member_id')->all(),
            'rating' => $avg !== null ? round((float) $avg, 1) : null,
            'tasks' => $this->tasksCreated($m),
            'solved' => Problem::where('meeting_id', $m->id)->where('status', 'solved')->get(),
            'minutes' => $m->ended_at ? (int) $m->started_at->diffInMinutes($m->ended_at) : null,
        ];
    }
}
