<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Procedure;
use App\Models\ProcedureVersion;
use App\Models\SyncAudit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Standardization and Synchronization Cycle rules (PRD section 15). */
class Procedures
{
    /** Audience: the owning team and every team beneath it, or the whole organization. */
    public function inAudience(Procedure $p, ?int $memberId, Formation $f): bool
    {
        if (! $memberId) {
            return false;
        }
        if ($p->audience === 'organization') {
            return true;
        }

        return (bool) array_intersect($f->seatIdsHeldBy($memberId), [$p->team_seat_id, ...$f->descendantIds($p->team_seat_id)]);
    }

    /** @return int[] active members in the audience */
    public function audience(Procedure $p, Formation $f): array
    {
        $active = Membership::active()->pluck('id')->all();
        if ($p->audience === 'organization') {
            return $active;
        }
        $seats = [$p->team_seat_id, ...$f->descendantIds($p->team_seat_id)];

        return array_values(array_intersect($active, array_unique(array_filter(array_map(fn ($id) => $f->seat($id)?->holder_id, $seats)))));
    }

    /** Markdown rendered server-side with raw HTML stripped, unsafe links blocked, and no images. */
    public static function render(?string $md): string
    {
        $html = Str::markdown((string) $md, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return preg_replace('/<img[^>]*>/i', '', $html);
    }

    public static function words(?string $md): int
    {
        return str_word_count(strip_tags((string) $md));
    }

    public function publish(Procedure $p, int $by): ProcedureVersion
    {
        return DB::transaction(function () use ($p, $by) {
            $p->version = $p->version + 1;
            $p->published_at = now();
            $p->reviewed_at = now()->toDateString();
            $p->save();
            $v = new ProcedureVersion;
            $v->procedure_id = $p->id;
            $v->version = $p->version;
            $v->body = (string) $p->body;
            $v->published_by = $by;
            $v->published_at = now();
            $v->save();

            return $v;
        });
    }

    /**
     * SY-03: in sync when every audience member acknowledged the current version, the latest audit
     * is within the review interval and "followed", and the review is not overdue.
     *
     * @return array{in_sync: bool, reasons: string[], acked: int, total: int, audit: ?SyncAudit, review_due: ?CarbonInterface}
     */
    public function status(Procedure $p, Formation $f, CarbonInterface $today): array
    {
        $audience = $this->audience($p, $f);
        $acked = $p->version ? DB::table('procedure_acks')->where('procedure_id', $p->id)->where('version', $p->version)->whereIn('member_id', $audience)->count() : 0;
        $audit = SyncAudit::where('procedure_id', $p->id)->orderByDesc('audited_on')->orderByDesc('id')->first();
        $due = $p->reviewDue();
        $reasons = [];
        if (! $p->version) {
            $reasons[] = 'Not published yet';
        } elseif ($acked < count($audience)) {
            $reasons[] = (count($audience) - $acked).' of '.count($audience)." haven't acknowledged v{$p->version}";
        }
        if (! $audit) {
            $reasons[] = 'No audit yet';
        } else {
            if ($audit->audited_on->lt($today->copy()->subMonths($p->review_every_months))) {
                $reasons[] = 'Last audit is older than '.$p->review_every_months.' months';
            }
            if ($audit->result !== 'followed') {
                $reasons[] = 'Last audit: '.($audit->result === 'partly' ? 'partly followed' : 'not followed');
            }
        }
        if ($due && $due->lte($today)) {
            $reasons[] = 'Review due';
        }

        return ['in_sync' => ! $reasons, 'reasons' => $reasons, 'acked' => $acked, 'total' => count($audience), 'audit' => $audit, 'review_due' => $due];
    }
}
