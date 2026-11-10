<?php

namespace App\Services;

use App\Http\Controllers\Org\FormationController;
use App\Models\AlignmentRound;
use App\Models\AssessmentAssessor;
use App\Models\DiagnosticCycle;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricValue;
use App\Models\Mission;
use App\Models\Problem;
use App\Models\Procedure;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/** Everything Home shows for one person, built in one request (PRD H-02). Empty blocks are omitted by the view. */
class HomeBlocks
{
    public function __construct(private Access $access, private Tenant $tenant) {}

    public function build(): array
    {
        $me = $this->access->id();
        $f = $this->access->formation();
        $cal = Calendar::for($this->tenant->organization);
        $week = $cal->currentWeekStart();
        $today = $cal->today();
        $mySeats = $f->seatIdsHeldBy($me);
        $myTeams = $f->teamIdsOf($me);

        $banners = [];
        $round = AlignmentRound::whereNull('closed_at')->first();
        if ($round && ! DB::table('alignment_completions')->where('round_id', $round->id)->where('member_id', $me)->exists()) {
            $banners[] = ['Alignment Check is open until '.Calendar::formatDate($cal->local($round->closes_at)).'.', 'Answer now', route('org.alignment.index')];
        }
        $cycle = DiagnosticCycle::whereNull('closed_at')->first();
        if ($cycle && ! DB::table('diagnostic_completions')->where('cycle_id', $cycle->id)->where('member_id', $me)->exists()) {
            $banners[] = ['The Company Diagnostic is open until '.Calendar::formatDate($cal->local($cycle->closes_at)).'.', 'Answer now', route('org.diagnostic.index')];
        }
        $pending360 = AssessmentAssessor::where('assessor_id', $me)->whereNull('completed_at')
            ->whereHas('assignment.cycle', fn ($q) => $q->where('status', 'open'))->count();
        if ($pending360) {
            $banners[] = [$pending360.' '.($pending360 === 1 ? 'assessment' : 'assessments').' to complete.', 'Open 360', route('org.assessment.index')];
        }
        $toRead = $this->unacknowledged($me, $myTeams);
        foreach ($toRead as $p) {
            $banners[] = ['To read: '.$p->title.' v'.$p->version.'.', 'Read it', route('org.procedures.show', $p)];
        }

        $myMetrics = Metric::whereIn('owner_seat_id', $mySeats ?: [0])->whereNull('archived_at')->get();
        $entered = MetricValue::whereIn('metric_id', $myMetrics->pluck('id'))->where('week_start', $week->toDateString())->pluck('metric_id')->all();
        $numbers = $myMetrics->reject(fn ($m) => in_array($m->id, $entered, true))->values();
        $tasks = Task::whereIn('status', ['todo', 'doing'])->where(fn ($q) => $q->where('responsible_id', $me)->orWhere('accountable_id', $me))
            ->where('due_date', '<=', $week->addDays(6)->toDateString())->orderBy('due_date')->limit(25)->get();
        $stale = Mission::where('owner_id', $me)->whereIn('status', ['on_track', 'off_track'])
            ->where(fn ($q) => $q->whereNull('status_updated_at')->orWhere('status_updated_at', '<', now()->subDays(7)))->get();

        $meetings = [];
        foreach ($myTeams as $team) {
            $next = Event::where('type', 'weekly_meeting')->where('team_seat_id', $team)->where('ends_at', '>', now()->subHours(3))->orderBy('starts_at')->first();
            if ($next) {
                $live = Meeting::where('event_id', $next->id)->whereNotNull('started_at')->whereNull('ended_at')->exists();
                $meetings[] = ['event' => $next, 'team' => $f->seat($team)->teamLabel(), 'live' => $live];
            }
        }

        $leader = [];
        $subTeams = $f->teamsInSubtree($me);
        if ($subTeams) {
            $subSeats = array_values(array_unique(array_merge($f->subtreeSeatIds($me), $mySeats)));
            $teamMetrics = Metric::whereIn('team_seat_id', $subTeams)->whereNull('archived_at')->get();
            $last = $week->subWeek()->toDateString();
            $lastVals = MetricValue::whereIn('metric_id', $teamMetrics->pluck('id'))->where('week_start', $last)->get()->keyBy('metric_id');
            $leader['metrics'] = $teamMetrics->map(fn ($m) => ['m' => $m, 'state' => isset($lastVals[$m->id]) ? $lastVals[$m->id]->status : 'missing'])
                ->filter(fn ($x) => $x['state'] !== 'on')->values();
            $leader['evaluate'] = Problem::with('solve')->where('status', 'solved')->whereIn('team_seat_id', $subTeams)
                ->whereHas('solve', fn ($q) => $q->whereNull('evaluation_result')->where('evaluate_on', '<=', $today->toDateString()))->get();
            $leader['closing'] = Mission::whereIn('status', ['on_track', 'off_track'])->where('quarter_start', '<', $cal->currentQuarterStart()->toDateString())
                ->where(fn ($q) => $q->whereIn('team_seat_id', $subTeams)->orWhereIn('owner_id', $this->holders($f, $subSeats)))->get();
            $leader['reviews'] = Procedure::whereNull('archived_at')->whereNotNull('reviewed_at')->whereIn('team_seat_id', $subTeams)->get()
                ->filter(fn ($p) => $p->reviewDue()?->lte($today))->values();
            $vacant = array_values(array_filter($subSeats, fn ($id) => ! $f->seat($id)?->holder_id));
            $owned = FormationController::ownedCounts($vacant);
            $leader['vacant'] = collect($vacant)->map(fn ($id) => ['seat' => $f->seat($id), 'metrics' => $owned['metrics'][$id] ?? 0, 'procedures' => $owned['procedures'][$id] ?? 0])
                ->filter(fn ($x) => $x['metrics'] || $x['procedures'])->values();
            $owners = Metric::whereNull('archived_at')->pluck('owner_seat_id')->unique()->all();
            $noNumbers = collect($subSeats)->filter(fn ($id) => $f->seat($id)?->holder_id && ! in_array($id, $owners, true))
                ->map(fn ($id) => $f->seat($id)->holder_id)->unique()->reject(fn ($id) => collect($f->seatIdsHeldBy($id))->contains(fn ($s) => in_array($s, $owners, true)));
            $leader['noNumbers'] = Membership::with('user')->whereIn('id', $noNumbers->all() ?: [0])->get();
            $leader = array_filter($leader, fn ($v) => count($v) > 0);
        }

        $todayEvents = Event::visibleTo($this->access->me())
            ->where(fn ($q) => $q->where('created_by', $me)->orWhere('partner_id', $me)->orWhere('scope', 'org')->orWhereIn('team_seat_id', $myTeams ?: [0]))
            ->whereBetween('starts_at', [$today->utc(), $today->addDay()->utc()])->orderBy('starts_at')->get();

        return compact('banners', 'numbers', 'tasks', 'stale', 'meetings', 'leader', 'todayEvents', 'cal', 'today');
    }

    private function holders(Formation $f, array $seats): array
    {
        return array_values(array_filter(array_map(fn ($id) => $f->seat($id)?->holder_id, $seats)));
    }

    /** Published procedures whose audience includes me and that I haven't acknowledged at their current version. */
    public function unacknowledged(?int $me, array $myTeams)
    {
        $f = $this->access->formation();
        $acked = DB::table('procedure_acks')->where('member_id', $me)->get()->map(fn ($r) => $r->procedure_id.':'.$r->version)->all();

        return Procedure::whereNull('archived_at')->where('version', '>', 0)->get()
            ->filter(fn ($p) => app(Procedures::class)->inAudience($p, $me, $f) && ! in_array($p->id.':'.$p->version, $acked, true))->values();
    }
}
