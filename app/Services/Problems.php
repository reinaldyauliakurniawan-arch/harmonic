<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Problem;
use App\Models\SolveRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Problem Queue and I-S.O.L.V.E rules (PRD section 14). */
class Problems
{
    public const SOURCES = ['metric' => 'Metric', 'mission' => 'Mission', 'alignment' => 'Alignment comment', 'headline' => 'Headline',
        'problem' => 'Earlier problem', 'diagnostic' => 'Company Diagnostic', 'audit' => 'Procedure audit'];

    public function __construct(private Access $access, private Tenant $tenant) {}

    public function raise(array $d, ?string $sourceType = null, ?int $sourceId = null): Problem
    {
        $f = $this->access->formation();
        $team = (int) $d['team_seat_id'];
        if (! $f->isTeam($team)) {
            throw ValidationException::withMessages(['team_seat_id' => 'Choose a team.']);
        }
        $owner = $d['owner_id'] ?? null;
        if ($owner && ! in_array((int) $owner, $f->teamMemberIds($team), true)) {
            throw ValidationException::withMessages(['owner_id' => 'The owner must be a member of that team.']);
        }
        $p = new Problem(['team_seat_id' => $team, 'title' => $d['title'], 'description' => $d['description'] ?? null, 'horizon' => $d['horizon'] ?? 'short']);
        $p->owner_id = $owner ? (int) $owner : null;
        $p->raised_by = $this->access->id();
        $p->status = 'open';
        $p->source_type = $sourceType;
        $p->source_id = $sourceId;
        $p->save();

        return $p;
    }

    /** Ranks 1–3 are unique per team and horizon; moving one in pushes the others down and rank 3 out (P-03). */
    public function rank(Problem $p, ?int $rank): void
    {
        DB::transaction(function () use ($p, $rank) {
            $p->rank = null;
            $p->save();
            if ($rank) {
                $others = Problem::where('team_seat_id', $p->team_seat_id)->where('horizon', $p->horizon)
                    ->whereIn('status', ['open', 'solving'])->whereNotNull('rank')->where('id', '!=', $p->id)
                    ->orderBy('rank')->get()->values();
                $order = $others->pluck('id')->all();
                array_splice($order, $rank - 1, 0, [$p->id]);
                foreach ($order as $i => $id) {
                    Problem::whereKey($id)->update(['rank' => $i < 3 ? $i + 1 : null]);
                }
            }
        });
        $p->refresh();
    }

    public function clearRanks(int $team): void
    {
        Problem::where('team_seat_id', $team)->update(['rank' => null]);
    }

    public function drop(Problem $p, string $reason, ?int $duplicateOf = null): void
    {
        $p->status = 'dropped';
        $p->rank = null;
        $p->drop_reason = $reason;
        $p->duplicate_of_id = $duplicateOf;
        $p->save();
    }

    public function start(Problem $p, string $mode, ?int $meetingId = null): SolveRecord
    {
        return DB::transaction(function () use ($p, $mode, $meetingId) {
            $rec = $p->solve ?? new SolveRecord;
            if ($rec->exists && $rec->mode === 'full' && $mode === 'quick') {
                throw ValidationException::withMessages(['mode' => 'A full solve can\'t go back to quick mode.']);
            }
            $rec->problem_id = $p->id;
            $rec->mode = $mode;
            $rec->save();
            $p->status = $p->status === 'solved' ? 'solved' : 'solving';
            $p->meeting_id = $meetingId ?? $p->meeting_id;
            $p->save();

            return $rec;
        });
    }

    public function markSolved(Problem $p, ?int $meetingId = null): void
    {
        $p->status = 'solved';
        $p->solved_at = now();
        $p->rank = null;
        $p->meeting_id = $meetingId ?? $p->meeting_id;
        $p->save();
    }

    public function teamOwnerOptions(int $team): array
    {
        return Membership::with('user')->whereIn('id', $this->access->formation()->teamMemberIds($team))->get()
            ->mapWithKeys(fn ($m) => [$m->id => $m->name])->all();
    }
}
