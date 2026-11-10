<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Models\Mission;
use App\Models\Problem;
use App\Models\Seat;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Meetings;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;

/** Derived teams (PRD W-07) and team cadence (ME-01). */
class TeamController extends Controller
{
    /** @return array<int, int> team id => count of active metrics whose latest saved week is off */
    public static function offTrackCounts(array $teamIds): array
    {
        $metrics = Metric::whereIn('team_seat_id', $teamIds ?: [0])->whereNull('archived_at')->get(['id', 'team_seat_id']);
        $latest = MetricValue::whereIn('metric_id', $metrics->pluck('id'))->orderByDesc('week_start')->get(['metric_id', 'status', 'week_start'])->unique('metric_id')->keyBy('metric_id');
        $out = [];
        foreach ($metrics as $m) {
            if (($latest[$m->id]->status ?? null) === 'off') {
                $out[$m->team_seat_id] = ($out[$m->team_seat_id] ?? 0) + 1;
            }
        }

        return $out;
    }

    public function index(Access $access, Tenant $tenant)
    {
        $this->authorize('viewAny', Seat::class);
        $f = $access->formation();
        $ids = array_map(fn ($s) => $s->id, $f->teams());
        $cal = Calendar::for($tenant->organization);
        $members = Membership::with('user')->whereIn('id', collect($ids)->flatMap(fn ($id) => $f->teamMemberIds($id))->unique())->get()->keyBy('id');

        return view('teams.index', [
            'f' => $f, 'teams' => $f->teams(), 'members' => $members, 'off' => self::offTrackCounts($ids),
            'problems' => Problem::whereIn('team_seat_id', $ids ?: [0])->whereIn('status', ['open', 'solving'])->selectRaw('team_seat_id, count(*) c')->groupBy('team_seat_id')->pluck('c', 'team_seat_id'),
            'missions' => Mission::whereIn('team_seat_id', $ids ?: [0])->whereIn('status', ['on_track', 'off_track'])->where('quarter_start', $cal->currentQuarterStart()->toDateString())->selectRaw('team_seat_id, count(*) c')->groupBy('team_seat_id')->pluck('c', 'team_seat_id'),
        ]);
    }

    public function show(Seat $seat, Access $access, Tenant $tenant, Tasks $tasks)
    {
        $this->authorize('viewAny', Seat::class);
        $f = $access->formation();
        abort_unless($f->isTeam($seat->id), 404);
        $cal = Calendar::for($tenant->organization);
        $lastWeek = $cal->currentWeekStart()->subWeek();

        return view('teams.show', [
            'team' => $seat, 'f' => $f, 'members' => Membership::with('user')->whereIn('id', $f->teamMemberIds($seat->id))->get(),
            'canLead' => auth()->user()->can('lead', $seat), 'rate' => $tasks->completionRate($seat->id, $lastWeek), 'lastWeek' => $lastWeek,
            'next' => Event::where('type', 'weekly_meeting')->where('team_seat_id', $seat->id)->where('ends_at', '>', now())->orderBy('starts_at')->first(),
            'cal' => $cal, 'minutes' => Meetings::minutes((int) $seat->meeting_minutes),
        ]);
    }

    public function cadence(Request $request, Seat $seat, Meetings $meetings, Access $access)
    {
        $this->authorize('lead', $seat);
        abort_unless($access->formation()->isTeam($seat->id), 404);
        $d = $request->validate(['meeting_weekday' => 'required|integer|between:1,7', 'meeting_time' => 'required|date_format:H:i', 'meeting_minutes' => 'required|integer|between:60,120']);
        $meetings->setCadence($seat, (int) $d['meeting_weekday'], $d['meeting_time'], (int) $d['meeting_minutes']);

        return back()->with('status', 'Cadence saved. Weekly Meetings are on the calendar');
    }
}
