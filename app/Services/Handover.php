<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AssessmentAssessor;
use App\Models\Board;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Mission;
use App\Models\Problem;
use App\Models\Procedure;
use App\Models\Seat;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only place ownership is reassigned (PRD section 8). A trigger describes the change;
 * plan() lists what would lose its owner, execute() reassigns it and performs the change in one transaction.
 *
 * Triggers: deactivate{member} · account{member} · vacate{seat, holder?} · move{seat, parent, destination?}
 *           · delete_seat{seat, replacement?, destination?}
 */
class Handover
{
    public function __construct(private Access $access, private Tenant $tenant) {}

    public function validateTrigger(array $t): array
    {
        $f = $this->access->formation();
        $type = $t['type'] ?? null;
        $seat = isset($t['seat']) ? $f->seat((int) $t['seat']) : null;
        abort_unless(in_array($type, ['deactivate', 'account', 'vacate', 'move', 'delete_seat'], true), 404);
        if (in_array($type, ['deactivate', 'account'], true)) {
            $m = Membership::active()->findOrFail((int) ($t['member'] ?? 0));
            if ($m->is_org_admin && Membership::active()->where('is_org_admin', true)->count() === 1) {
                throw ValidationException::withMessages(['member' => 'Make someone else an admin first.']);
            }

            return ['type' => $type, 'member' => $m->id];
        }
        abort_unless($seat, 404);
        $out = ['type' => $type, 'seat' => $seat->id];
        if ($type === 'vacate') {
            $holder = $t['holder'] ?? null;
            if ($holder) {
                $holder = Membership::active()->findOrFail((int) $holder)->id;
            }
            $out['holder'] = $holder ?: null;
        }
        if ($type === 'move') {
            $parent = $f->seat((int) ($t['parent'] ?? 0));
            abort_unless($parent && $seat->parent_id, 404);
            if ($parent->id === $seat->id || in_array($parent->id, $f->descendantIds($seat->id), true)) {
                throw ValidationException::withMessages(['parent' => "A seat can't move under its own child."]);
            }
            $out['parent'] = $parent->id;
        }
        if ($type === 'delete_seat') {
            abort_unless($seat->parent_id, 404);
            if ($f->childIds($seat->id)) {
                throw ValidationException::withMessages(['seat' => 'Move or delete its child seats first.']);
            }
            $out['replacement'] = isset($t['replacement']) && $t['replacement'] ? (int) $t['replacement'] : null;
        }
        if (in_array($type, ['move', 'delete_seat'], true)) {
            $out['destination'] = isset($t['destination']) && $t['destination'] ? (int) $t['destination'] : null;
        }

        return $out;
    }

    /** Seat attribute changes the trigger makes, keyed by seat id, for Formation::with(). */
    private function seatChanges(array $t): array
    {
        return match ($t['type']) {
            'deactivate', 'account' => collect($this->access->formation()->seatIdsHeldBy($t['member']))->mapWithKeys(fn ($id) => [$id => ['holder_id' => null]])->all(),
            'vacate' => [$t['seat'] => ['holder_id' => $t['holder']]],
            'move' => [$t['seat'] => ['parent_id' => $t['parent']]],
            'delete_seat' => [$t['seat'] => ['__deleted' => true]],
        };
    }

    /** The team whose last child leaves, if the trigger dissolves one (PRD W-06). */
    public function dissolvedTeam(array $t): ?int
    {
        if (! in_array($t['type'], ['move', 'delete_seat'], true)) {
            return null;
        }
        $before = $this->access->formation();
        $after = $before->with($this->seatChanges($t));
        $old = $before->seat($t['seat'])->parent_id;

        return $before->isTeam($old) && ! $after->isTeam($old) ? $old : null;
    }

    public function dissolutionCounts(int $team): array
    {
        $now = now();

        return array_filter([
            'metrics' => Metric::where('team_seat_id', $team)->whereNull('archived_at')->count(),
            'problems' => Problem::where('team_seat_id', $team)->whereIn('status', ['open', 'solving'])->count(),
            'missions' => Mission::where('team_seat_id', $team)->whereIn('status', ['on_track', 'off_track'])->count(),
            'boards' => Board::where('team_seat_id', $team)->whereNull('archived_at')->count(),
            'meetings' => Meeting::where('team_seat_id', $team)->whereNull('started_at')->where('scheduled_for', '>', $now)->count(),
            'procedures' => Procedure::where('team_seat_id', $team)->whereNull('archived_at')->count(),
        ]);
    }

    /** Seat-owned metrics and procedures that need a replacement owner seat when a seat is deleted (W-05). */
    public function ownedBySeat(int $seatId): array
    {
        return array_filter([
            'metrics' => Metric::where('owner_seat_id', $seatId)->count(),
            'procedures' => Procedure::where('owner_seat_id', $seatId)->count(),
        ]);
    }

    /**
     * @return array<int, array{member: Membership, departing: bool, default: ?int, items: array<string, array>}>
     */
    public function plan(array $t): array
    {
        $before = $this->access->formation();
        $after = $before->with($this->seatChanges($t));
        $people = [];

        if (in_array($t['type'], ['deactivate', 'account'], true)) {
            $people[$t['member']] = null;
        } else {
            $holders = array_unique(array_filter(array_map(fn ($s) => $s->holder_id, $before->seats)));
            foreach ($holders as $pid) {
                $left = array_values(array_diff($before->teamIdsOf($pid), $after->teamIdsOf($pid)));
                if ($left) {
                    $people[$pid] = $left;
                }
            }
        }

        $members = Membership::with('user')->whereIn('id', array_keys($people))->get()->keyBy('id');
        $out = [];
        foreach ($people as $pid => $teams) {
            $items = $this->items($pid, $teams);
            $out[$pid] = [
                'member' => $members[$pid],
                'departing' => $teams === null,
                'teams' => $teams,
                'default' => $this->defaultReceiver($t, $pid, $before),
                'items' => $items,
            ];
        }

        return $out;
    }

    /** @param int[]|null $teams null = departure, every open item */
    public function items(int $pid, ?array $teams): array
    {
        $scope = fn ($q) => $teams === null ? $q : $q->whereIn('team_seat_id', $teams);

        return [
            'tasks' => $scope(Task::whereIn('status', ['todo', 'doing'])->where(fn ($q) => $q->where('responsible_id', $pid)->orWhere('accountable_id', $pid)))->orderBy('due_date')->get(),
            'missions' => $scope(Mission::whereIn('status', ['on_track', 'off_track'])->where('owner_id', $pid))->get(),
            'problems' => $scope(Problem::whereIn('status', ['open', 'solving'])->where('owner_id', $pid))->get(),
            'events' => $scope(Event::whereIn('scope', ['org', 'team'])->where('created_by', $pid)->where('starts_at', '>', now()))->orderBy('starts_at')->get(),
        ];
    }

    public function defaultReceiver(array $t, int $pid, Formation $f): ?int
    {
        if ($t['type'] === 'vacate' && $t['holder'] && $t['holder'] !== $pid) {
            return $t['holder'];
        }
        $seats = in_array($t['type'], ['deactivate', 'account'], true) ? $f->seatIdsHeldBy($pid) : [$t['seat']];
        foreach ($seats as $seatId) {
            if ($h = $f->nearestHolderAbove($seatId, $pid)) {
                return $h;
            }
        }
        $me = $this->access->id();

        return $me !== $pid ? $me : Membership::active()->where('is_org_admin', true)->where('id', '!=', $pid)->value('id');
    }

    /**
     * @param  array<int, array{all?: int, items?: array<string, int>}>  $receivers  per person
     */
    public function execute(array $t, array $receivers): void
    {
        $plan = $this->plan($t);
        $dissolved = $this->dissolvedTeam($t);
        if ($dissolved && $this->dissolutionCounts($dissolved)) {
            $f = $this->access->formation()->with($this->seatChanges($t));
            if (! $t['destination'] || ! $f->isTeam($t['destination']) || $t['destination'] === $dissolved) {
                throw ValidationException::withMessages(['destination' => 'Choose the team that takes over its metrics, problems, Missions, boards, and meetings.']);
            }
        }
        if ($t['type'] === 'delete_seat' && $this->ownedBySeat($t['seat'])) {
            $f = $this->access->formation();
            if (! $t['replacement'] || ! $f->seat($t['replacement']) || $t['replacement'] === $t['seat']) {
                throw ValidationException::withMessages(['replacement' => 'Choose the seat that takes over its metrics and procedures.']);
            }
        }

        $active = Membership::active()->pluck('id')->all();
        DB::transaction(function () use ($t, $plan, $receivers, $active, $dissolved) {
            $counts = [];
            foreach ($plan as $pid => $p) {
                $all = (int) ($receivers[$pid]['all'] ?? $p['default']);
                foreach ($p['items'] as $type => $rows) {
                    foreach ($rows as $row) {
                        $to = (int) ($receivers[$pid]['items'][$type.':'.$row->id] ?? 0) ?: $all;
                        if (! in_array($to, $active, true) || $to === $pid) {
                            throw ValidationException::withMessages(['receiver' => 'Choose an active member other than the person leaving.']);
                        }
                        $this->reassign($type, $row, $pid, $to);
                        $counts[$type] = ($counts[$type] ?? 0) + 1;
                    }
                }
                if ($p['departing']) {
                    DB::table('task_people')->where('member_id', $pid)->whereIn('task_id', Task::withTrashed()->select('id'))->delete();
                    AssessmentAssessor::where('assessor_id', $pid)->whereNull('completed_at')->delete();
                }
            }

            $this->perform($t, $dissolved);
            ActivityLog::record('handover.completed', null, ['trigger' => $t['type'], 'counts' => $counts]);
        });
        $this->access->refresh();
    }

    private function reassign(string $type, $row, int $from, int $to): void
    {
        if ($type === 'tasks') {
            if ($row->responsible_id === $from) {
                $row->responsible_id = $to;
            }
            if ($row->accountable_id === $from) {
                $row->accountable_id = $to;
            }
            $row->save();
            DB::table('task_people')->where('task_id', $row->id)->where('member_id', $to)->delete();
        } elseif ($type === 'events') {
            $row->created_by = $to;
            $row->save();
        } else {
            $row->owner_id = $to;
            $row->save();
        }
    }

    private function perform(array $t, ?int $dissolved): void
    {
        $seat = isset($t['seat']) ? Seat::find($t['seat']) : null;
        switch ($t['type']) {
            case 'deactivate':
            case 'account':
                $m = Membership::findOrFail($t['member']);
                foreach (Seat::where('holder_id', $m->id)->get() as $s) {
                    $s->holder_id = null;
                    $s->save();
                    ActivityLog::record('seat.vacated', $s, ['member' => $m->id]);
                }
                $m->status = 'departed';
                $m->departed_at = now();
                $m->save();
                ActivityLog::record('member.departed', $m);
                $m->user->invalidateSessions();
                if ($t['type'] === 'account' && ! Membership::withoutGlobalScope('organization')->where('user_id', $m->user_id)->where('status', 'active')->exists()) {
                    self::anonymize($m->user);
                }
                break;
            case 'vacate':
                $old = $seat->holder_id;
                $seat->holder_id = $t['holder'];
                $seat->save();
                if ($old) {
                    ActivityLog::record('seat.vacated', $seat, ['member' => $old]);
                }
                if ($t['holder']) {
                    ActivityLog::record('seat.assigned', $seat, ['member' => $t['holder']]);
                }
                break;
            case 'move':
                $seat->parent_id = $t['parent'];
                $seat->sort_order = (int) Seat::where('parent_id', $t['parent'])->max('sort_order') + 1;
                $seat->save();
                ActivityLog::record('seat.moved', $seat, ['parent' => $t['parent']]);
                break;
            case 'delete_seat':
                if ($t['replacement']) {
                    Metric::where('owner_seat_id', $seat->id)->update(['owner_seat_id' => $t['replacement']]);
                    Procedure::where('owner_seat_id', $seat->id)->update(['owner_seat_id' => $t['replacement']]);
                }
                ActivityLog::record('seat.deleted', $seat, ['title' => $seat->title]);
                Invitation::where('seat_id', $seat->id)->update(['seat_id' => null]);
                $seat->delete();
                break;
        }
        if ($dissolved && $t['destination']) {
            $to = $t['destination'];
            Metric::where('team_seat_id', $dissolved)->whereNull('archived_at')->update(['team_seat_id' => $to]);
            Problem::where('team_seat_id', $dissolved)->whereIn('status', ['open', 'solving'])->update(['team_seat_id' => $to, 'rank' => null]);
            Mission::where('team_seat_id', $dissolved)->whereIn('status', ['on_track', 'off_track'])->update(['team_seat_id' => $to]);
            Board::where('team_seat_id', $dissolved)->whereNull('archived_at')->update(['team_seat_id' => $to]);
            Meeting::where('team_seat_id', $dissolved)->whereNull('started_at')->where('scheduled_for', '>', now())->update(['team_seat_id' => $to]);
            // Procedures aren't listed in W-06, but leaving them on a dissolved team would orphan them.
            Procedure::where('team_seat_id', $dissolved)->whereNull('archived_at')->update(['team_seat_id' => $to]);
        }
    }

    public static function anonymize(User $user): void
    {
        $user->forceFill([
            'name' => 'Former member',
            'email' => 'deleted+'.$user->id.'@invalid.local',
            'password' => Str::random(40),
            'anonymized_at' => now(),
            'remember_token' => null,
            'sessions_valid_from' => now()->addSecond(),
        ])->save();
    }
}
