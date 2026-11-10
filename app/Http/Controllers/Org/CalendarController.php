<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Recurrence;
use App\Services\Tasks;
use App\Services\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Unified Calendar (PRD section 10). */
class CalendarController extends Controller
{
    public const TYPES = [
        'custom' => 'Event', 'quarterly_session' => 'Quarterly session', 'annual_session' => 'Annual session',
        'guide_checkin' => 'Quarterly Check-in', 'guide_shoutout' => 'Belief Shoutout',
        'guide_reflection' => 'Heart & Head Reflection', 'guide_passion' => 'Passion-Performance Matrix',
        'weekly_meeting' => 'Weekly Meeting', 'diagnostic' => 'Company Diagnostic', 'assessment' => 'Leadership Assessment 360',
    ];

    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $cal = Calendar::for($tenant->organization);
        $q = $request->validate([
            'view' => 'nullable|in:month,week,agenda', 'date' => 'nullable|date', 'filter' => 'nullable|in:all,mine,org,team',
            'team' => 'nullable|integer', 'due' => 'nullable|boolean', 'event' => 'nullable|integer', 'new' => 'nullable|boolean',
        ]);
        $view = $q['view'] ?? 'month';
        $date = isset($q['date']) ? CarbonImmutable::parse($q['date'], $cal->timezone) : $cal->today();
        [$from, $to] = match ($view) {
            'week' => [$cal->weekStart($date), $cal->weekStart($date)->addDays(7)],
            'agenda' => [$date->startOfDay(), $date->startOfDay()->addDays(28)],
            default => [$cal->weekStart($date->startOfMonth()), $cal->weekStart($date->endOfMonth())->addDays(7)],
        };
        $me = $access->id();
        $f = $access->formation();
        $myTeams = $f->teamIdsOf($me);
        $filter = $q['filter'] ?? 'all';

        $events = Event::visibleTo($access->me())
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>=', $from->utc())
            ->when($filter === 'mine', fn ($w) => $w->where(fn ($w) => $w->where('created_by', $me)->orWhere('partner_id', $me)->orWhereIn('team_seat_id', $myTeams)))
            ->when($filter === 'org', fn ($w) => $w->where('scope', 'org'))
            ->when($filter === 'team' && ($q['team'] ?? null), fn ($w) => $w->where('team_seat_id', $q['team']))
            ->orderBy('starts_at')->get();

        $markers = collect();
        if ($q['due'] ?? false) {
            $markers = Task::whereIn('status', ['todo', 'doing'])->where(fn ($w) => $w->where('responsible_id', $me)->orWhere('accountable_id', $me))
                ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn ($t) => ['date' => $t->due_date->toDateString(), 'label' => 'Due: '.$t->title, 'url' => route('org.tasks.show', $t)]);
            $missions = Mission::where('owner_id', $me)->whereIn('status', ['on_track', 'off_track'])->with('milestones')->get();
            foreach ($missions as $m) {
                $end = $cal->quarterEndFor($m->quarter_start);
                if ($end->between($from, $to)) {
                    $markers->push(['date' => $end->toDateString(), 'label' => 'Quarter ends: '.$m->title, 'url' => route('org.missions.show', $m)]);
                }
                foreach ($m->milestones as $ms) {
                    if ($ms->due_date && ! $ms->done_at && $ms->due_date->between($from, $to)) {
                        $markers->push(['date' => $ms->due_date->toDateString(), 'label' => 'Milestone: '.$ms->title, 'url' => route('org.missions.show', $m)]);
                    }
                }
            }
        }

        $panel = null;
        if ($q['event'] ?? null) {
            $panel = Event::with(['creator.user', 'partner.user'])->findOrFail($q['event']);
            $this->authorize('view', $panel);
        }

        return view('calendar.index', [
            'cal' => $cal, 'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to, 'events' => $events,
            'markers' => $markers->groupBy('date'), 'q' => $q, 'filter' => $filter, 'teams' => $f->teamOptions(),
            'panel' => $panel, 'guide' => $panel ? config('guides.'.$panel->type) : null,
            'panelTasks' => $panel ? Task::where('source_type', 'event')->where('source_id', $panel->id)->get() : collect(),
            'members' => Tasks::memberOptions(), 'creatable' => $this->creatableTypes($access),
            'partners' => $this->partnerOptions($access),
        ]);
    }

    private function creatableTypes(Access $access): array
    {
        $types = ['custom' => 'Event', 'guide_shoutout' => 'Belief Shoutout', 'guide_reflection' => 'Heart & Head Reflection', 'guide_passion' => 'Passion-Performance Matrix'];
        if ($access->formation()->ledTeamIds($access->id())) {
            $types += ['quarterly_session' => 'Quarterly session', 'annual_session' => 'Annual session'];
        }
        if ($this->partnerOptions($access)) {
            $types['guide_checkin'] = 'Quarterly Check-in';
        }

        return $types;
    }

    /** Holders of seats directly under a seat I hold. */
    private function partnerOptions(Access $access): array
    {
        $f = $access->formation();
        $ids = [];
        foreach ($f->seatIdsHeldBy($access->id()) as $sid) {
            foreach ($f->childIds($sid) as $c) {
                if ($h = $f->seat($c)->holder_id) {
                    $ids[] = $h;
                }
            }
        }

        return Membership::active()->with('user')->whereIn('id', array_unique($ids))->where('id', '!=', $access->id())->get()->mapWithKeys(fn ($m) => [$m->id => $m->name])->all();
    }

    private function scopeFor(string $type, ?string $scope): string
    {
        return match ($type) {
            'quarterly_session', 'annual_session' => 'team',
            'guide_checkin' => 'pair',
            'guide_reflection', 'guide_passion' => 'private',
            default => $scope ?? 'org',
        };
    }

    private function times(array $d, Calendar $cal): array
    {
        if ($d['all_day'] ?? false) {
            $start = CarbonImmutable::parse($d['date'], $cal->timezone)->startOfDay();

            return [$start->utc(), $start->endOfDay()->utc()];
        }
        $start = CarbonImmutable::parse($d['date'].' '.$d['start_time'], $cal->timezone);
        $end = CarbonImmutable::parse($d['date'].' '.$d['end_time'], $cal->timezone);
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_time' => 'End time must be after the start time.']);
        }

        return [$start->utc(), $end->utc()];
    }

    public function store(Request $request, Access $access, Tenant $tenant, Recurrence $recurrence)
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['custom', 'quarterly_session', 'annual_session', 'guide_checkin', 'guide_shoutout', 'guide_reflection', 'guide_passion'])],
            'title' => 'nullable|string|max:160',
            'scope' => 'nullable|in:org,team,private',
            'team_seat_id' => 'nullable|integer',
            'partner_id' => 'nullable|integer',
            'date' => 'required|date',
            'all_day' => 'boolean',
            'start_time' => 'required_unless:all_day,1|nullable|date_format:H:i',
            'end_time' => 'required_unless:all_day,1|nullable|date_format:H:i',
            'repeat' => 'nullable|in:none,weekly,biweekly,monthly,quarterly',
            'until' => 'nullable|date|after_or_equal:date',
            'notes' => 'nullable|string|max:10000',
        ]);
        $scope = $this->scopeFor($d['type'], $d['scope'] ?? null);
        $team = in_array($scope, ['team'], true) ? ($d['team_seat_id'] ?? null) : null;
        $partner = $scope === 'pair' ? ($d['partner_id'] ?? null) : null;
        $this->authorize('create', [Event::class, $d['type'], $scope, $team ? (int) $team : null, $partner ? (int) $partner : null]);
        $cal = Calendar::for($tenant->organization);
        [$start, $end] = $this->times($d, $cal);

        $event = DB::transaction(function () use ($d, $scope, $team, $partner, $start, $end, $access, $recurrence) {
            $event = new Event([
                'title' => ($d['title'] ?? null) ?: (config('guides.'.$d['type'].'.name') ?? self::TYPES[$d['type']]),
                'type' => $d['type'], 'scope' => $scope, 'team_seat_id' => $team, 'starts_at' => $start, 'ends_at' => $end,
                'all_day' => (bool) ($d['all_day'] ?? false), 'notes' => $d['notes'] ?? null,
            ]);
            $event->partner_id = $partner;
            $event->created_by = $access->id();
            $event->save();
            if (($d['repeat'] ?? 'none') !== 'none') {
                $recurrence->create($event, $d['repeat'], $d['until'] ?? null);
            }

            return $event;
        });

        return redirect()->route('org.calendar.index', ['date' => $d['date'], 'event' => $event->id])->with('status', 'Event created');
    }

    public function update(Request $request, Event $event, Tenant $tenant, Recurrence $recurrence)
    {
        $this->authorize('update', $event);
        $d = $request->validate([
            'title' => 'required|string|min:2|max:160', 'date' => 'required|date', 'all_day' => 'boolean',
            'start_time' => 'required_unless:all_day,1|nullable|date_format:H:i', 'end_time' => 'required_unless:all_day,1|nullable|date_format:H:i',
            'apply' => 'nullable|in:this,following',
        ]);
        $cal = Calendar::for($tenant->organization);
        [$start, $end] = $this->times($d, $cal);
        DB::transaction(function () use ($event, $d, $start, $end, $recurrence) {
            if ($event->series_id && ($d['apply'] ?? 'this') === 'following') {
                $shift = $event->starts_at->diffInSeconds($start, false);
                $length = $start->diffInSeconds($end);
                $new = $recurrence->split($event);
                foreach (Event::where('series_id', $new->id)->get() as $e) {
                    $s = $e->starts_at->copy()->addSeconds((int) $shift);
                    $e->fill(['title' => $d['title'], 'starts_at' => $s, 'ends_at' => $s->copy()->addSeconds((int) $length), 'all_day' => (bool) ($d['all_day'] ?? false)]);
                    $e->save();
                }
            } else {
                $event->fill(['title' => $d['title'], 'starts_at' => $start, 'ends_at' => $end, 'all_day' => (bool) ($d['all_day'] ?? false)]);
                $event->save();
            }
        });

        return redirect()->route('org.calendar.index', ['date' => $d['date'], 'event' => $event->id])->with('status', 'Saved');
    }

    public function destroy(Request $request, Event $event, Recurrence $recurrence)
    {
        $this->authorize('update', $event);
        $apply = $request->validate(['apply' => 'nullable|in:this,following'])['apply'] ?? 'this';
        DB::transaction(function () use ($event, $apply, $recurrence) {
            if ($event->series_id && $apply === 'following') {
                $new = $recurrence->split($event);
                Event::where('series_id', $new->id)->delete();
                $new->until = $event->starts_at->copy()->subDay()->toDateString();
                $new->save();
            } else {
                $event->delete();
            }
        });

        return redirect()->route('org.calendar.index')->with('status', 'Event deleted');
    }

    /** Autosave on blur (PRD C-04). */
    public function notes(Request $request, Event $event)
    {
        $this->authorize('notes', $event);
        $event->notes = $request->validate(['notes' => 'nullable|string|max:10000'])['notes'];
        $event->save();

        return response()->json(['saved' => true]);
    }
}
