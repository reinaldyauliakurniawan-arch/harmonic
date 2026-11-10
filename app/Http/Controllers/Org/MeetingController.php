<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingHeadline;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Models\Mission;
use App\Models\Problem;
use App\Models\Task;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Meetings;
use App\Services\Metrics;
use App\Services\Problems;
use App\Services\Tasks;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Meeting Cadence: history and the Weekly Meeting runner (PRD ME-02, ME-03). */
class MeetingController extends Controller
{
    public function index(Request $request, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $f = $access->formation();
        $teams = $f->teamOptions();
        $team = (int) ($request->query('team') && isset($teams[$request->query('team')]) ? $request->query('team') : ($f->teamIdsOf($access->id())[0] ?? array_key_first($teams)));
        $cal = Calendar::for($tenant->organization);
        $meetings = Meeting::where('team_seat_id', $team)->whereNotNull('started_at')->latest('scheduled_for')->paginate(25)->withQueryString();
        $ids = $meetings->pluck('id');
        $ratings = DB::table('meeting_ratings')->whereIn('meeting_id', $ids)->groupBy('meeting_id')->selectRaw('meeting_id, avg(score) a')->pluck('a', 'meeting_id');
        $solved = Problem::whereIn('meeting_id', $ids)->where('status', 'solved')->selectRaw('meeting_id, count(*) c')->groupBy('meeting_id')->pluck('c', 'meeting_id');
        $started = Meeting::whereNotNull('event_id')->pluck('event_id');
        $skipped = Event::where('type', 'weekly_meeting')->where('team_seat_id', $team)->where('ends_at', '<', now())->whereNotIn('id', $started)->latest('starts_at')->limit(10)->get();
        $last13 = Meeting::where('team_seat_id', $team)->whereNotNull('ended_at')->latest('scheduled_for')->limit(13)->get()->reverse()->values();
        $avg13 = DB::table('meeting_ratings')->whereIn('meeting_id', $last13->pluck('id'))->groupBy('meeting_id')->selectRaw('meeting_id, avg(score) a')->pluck('a', 'meeting_id');
        $upcoming = Event::where('type', 'weekly_meeting')->where('team_seat_id', $team)->where('ends_at', '>', now()->subHours(3))->orderBy('starts_at')->limit(3)->get();

        return view('meetings.index', compact('teams', 'team', 'meetings', 'ratings', 'solved', 'skipped', 'last13', 'avg13', 'upcoming', 'cal') + ['meetingsSvc' => app(Meetings::class)]);
    }

    public function run(Request $request, Event $event, Access $access, Tenant $tenant, Metrics $metrics, Tasks $tasks, Meetings $svc)
    {
        abort_unless($event->type === 'weekly_meeting', 404);
        $this->authorize('view', $tenant->organization);
        $meeting = Meeting::where('event_id', $event->id)->first();
        $team = $event->team_seat_id;
        $f = $access->formation();
        $seat = $f->seat($team);
        abort_unless($seat, 404);
        $data = ['event' => $event, 'meeting' => $meeting, 'team' => $seat, 'cal' => Calendar::for($tenant->organization),
            'canStart' => auth()->user()->can('start', [Meeting::class, $team]), 'segments' => Meetings::SEGMENTS,
            'minutes' => Meetings::minutes((int) ($seat->meeting_minutes ?: 90))];
        if (! $meeting || ! $meeting->isLive()) {
            $data['recap'] = $meeting ? $svc->recap($meeting) : null;
            $data['names'] = Membership::with('user')->whereIn('id', $f->teamMemberIds($team))->get()->keyBy('id');

            return view('meetings.run', $data);
        }

        $memberIds = $f->teamMemberIds($team);
        $cal = $data['cal'];
        $week = $cal->currentWeekStart();
        $seg = $meeting->segment;
        $data += [
            'participant' => auth()->user()->can('participate', $meeting), 'facilitator' => auth()->user()->can('facilitate', $meeting),
            'members' => Membership::with('user')->whereIn('id', $memberIds)->get()->keyBy('id'),
            'attendance' => DB::table('meeting_attendance')->where('meeting_id', $meeting->id)->pluck('present', 'member_id'),
            'ratings' => DB::table('meeting_ratings')->where('meeting_id', $meeting->id)->pluck('score', 'member_id'),
            'me' => $access->id(),
        ];
        if ($seg === 1) {
            $list = Metric::where('team_seat_id', $team)->whereNull('archived_at')->orderBy('sort_order')->get();
            $data['metrics'] = $list;
            $data['metricValues'] = MetricValue::whereIn('metric_id', $list->pluck('id'))->whereIn('week_start', [$week->toDateString(), $week->subWeek()->toDateString()])->get()->groupBy('metric_id')->map->keyBy(fn ($v) => $v->week_start->toDateString());
            $data['week'] = $week;
            $data['metricsSvc'] = $metrics;
        }
        if ($seg === 2) {
            $data['missions'] = Mission::with('owner.user', 'milestones')->whereIn('status', ['on_track', 'off_track'])->where('quarter_start', $cal->currentQuarterStart()->toDateString())
                ->where(fn ($q) => $q->where('team_seat_id', $team)->orWhere(fn ($q) => $q->where('level', 'personal')->whereIn('owner_id', $memberIds)))->get();
        }
        if ($seg === 3) {
            $data['headlines'] = MeetingHeadline::with('member.user')->where('meeting_id', $meeting->id)->get();
            $data['shoutouts'] = Event::where('type', 'guide_shoutout')->whereNotNull('notes')->where('starts_at', '>=', now()->subDays(7))->where(fn ($q) => $q->where('scope', 'org')->orWhere('team_seat_id', $team))->get();
        }
        if ($seg === 4) {
            $data['tasks'] = Task::with('responsible.user', 'steps')->whereIn('responsible_id', $memberIds)->whereIn('status', ['todo', 'doing', 'done'])
                ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('status', ['todo', 'doing'])->where('due_date', '<', $cal->today()->toDateString()))
                    ->orWhereBetween('due_date', [$cal->today()->subDays(7)->toDateString(), $cal->today()->toDateString()]))->orderBy('due_date')->get();
            $data['rate'] = $tasks->completionRate($team, $week->subWeek());
            $data['today'] = $cal->today();
        }
        if ($seg === 5) {
            $data['queue'] = Problem::with('owner.user', 'solve')->where('team_seat_id', $team)->where('horizon', 'short')->whereIn('status', ['open', 'solving'])->orderByRaw('`rank` is null')->orderBy('rank')->orderBy('created_at')->get();
            $data['evaluate'] = Problem::with('solve')->where('team_seat_id', $team)->where('status', 'solved')->whereHas('solve', fn ($q) => $q->whereNull('evaluation_result')->where('evaluate_on', '<=', $cal->today()->toDateString()))->get();
            $data['open'] = $request->query('problem') ? Problem::with('solve')->where('team_seat_id', $team)->find($request->query('problem')) : null;
            $data['openTasks'] = $data['open'] ? Task::where('source_type', 'problem')->where('source_id', $data['open']->id)->get() : collect();
        }
        if ($seg === 6) {
            $data['created'] = $svc->tasksCreated($meeting);
        }

        return view('meetings.run', $data);
    }

    public function start(Event $event, Meetings $svc)
    {
        abort_unless($event->type === 'weekly_meeting', 404);
        $this->authorize('start', [Meeting::class, $event->team_seat_id]);
        $svc->start($event);

        return redirect()->route('org.meetings.run', $event);
    }

    public function segment(Request $request, Meeting $meeting, Meetings $svc)
    {
        $this->authorize('facilitate', $meeting);
        $svc->go($meeting, (int) $request->validate(['segment' => 'required|integer|between:0,6'])['segment']);

        return redirect()->route('org.meetings.run', $meeting->event_id);
    }

    public function attendance(Request $request, Meeting $meeting, Access $access)
    {
        $this->authorize('participate', $meeting);
        $member = (int) $request->validate(['member_id' => 'required|integer'])['member_id'];
        abort_unless(in_array($member, $access->formation()->teamMemberIds($meeting->team_seat_id), true), 422);
        $present = ! (bool) DB::table('meeting_attendance')->where('meeting_id', $meeting->id)->where('member_id', $member)->value('present');
        DB::table('meeting_attendance')->updateOrInsert(['meeting_id' => $meeting->id, 'member_id' => $member], ['present' => $present]);
        if (! $present) {
            DB::table('meeting_ratings')->where('meeting_id', $meeting->id)->where('member_id', $member)->delete();
        }

        return back();
    }

    public function rate(Request $request, Meeting $meeting, Access $access)
    {
        $this->authorize('participate', $meeting);
        $score = (int) $request->validate(['score' => 'required|integer|between:1,10'])['score'];
        abort_unless(DB::table('meeting_attendance')->where('meeting_id', $meeting->id)->where('member_id', $access->id())->value('present'), 403);
        DB::table('meeting_ratings')->updateOrInsert(['meeting_id' => $meeting->id, 'member_id' => $access->id()], ['score' => $score]);

        return back()->with('status', 'Rating saved');
    }

    public function headline(Request $request, Meeting $meeting, Access $access)
    {
        $this->authorize('participate', $meeting);
        $d = $request->validate(['kind' => 'required|in:customer,employee', 'text' => 'required|string|min:2|max:280']);
        $h = new MeetingHeadline($d);
        $h->meeting_id = $meeting->id;
        $h->member_id = $access->id();
        $h->save();

        return back();
    }

    /** "Drop to problems" from metrics, Missions, and headlines. */
    public function drop(Request $request, Meeting $meeting, Problems $problems)
    {
        $this->authorize('participate', $meeting);
        $d = $request->validate(['type' => 'required|in:metric,mission,headline', 'id' => 'required|integer']);
        $title = match ($d['type']) {
            'metric' => Metric::where('team_seat_id', $meeting->team_seat_id)->findOrFail($d['id'])->name.' is off track',
            'mission' => Mission::findOrFail($d['id'])->title.' is off track',
            'headline' => MeetingHeadline::where('meeting_id', $meeting->id)->findOrFail($d['id'])->text,
        };
        $problems->raise(['team_seat_id' => $meeting->team_seat_id, 'title' => mb_substr($title, 0, 160), 'horizon' => 'short'], $d['type'], (int) $d['id']);

        return back()->with('status', 'Added to the Problem Queue');
    }

    public function notes(Request $request, Meeting $meeting)
    {
        $this->authorize('editNotes', $meeting);
        $d = $request->validate(['notes' => 'nullable|string|max:10000', 'cascade_message' => 'nullable|string|max:2000']);
        $meeting->fill([])->forceFill(array_intersect_key($d, array_flip(['notes', 'cascade_message'])))->save();

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('status', 'Saved');
    }

    public function end(Meeting $meeting, Meetings $svc)
    {
        $this->authorize('facilitate', $meeting);
        $svc->end($meeting);

        return redirect()->route('org.meetings.run', $meeting->event_id)->with('status', 'Meeting ended');
    }

    /** Polled every 15 s by participants while the meeting is live. */
    public function state(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'segment' => $meeting->segment, 'ended' => (bool) $meeting->ended_at,
            'version' => $meeting->updated_at?->timestamp.'-'.DB::table('meeting_headlines')->where('meeting_id', $meeting->id)->count()
                .'-'.Problem::where('team_seat_id', $meeting->team_seat_id)->max('updated_at'),
        ]);
    }

    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return redirect()->route('org.meetings.run', $meeting->event_id);
    }
}
