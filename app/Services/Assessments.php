<?php

namespace App\Services;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentCycle;
use App\Models\Event;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Leadership Assessment 360 (PRD section 18). */
class Assessments
{
    public function __construct(private Access $access, private Tenant $tenant) {}

    /** LA-03 and close: the lazy scheduler opens cycles on their open date and closes them after their close date. */
    public function advance(Organization $org): void
    {
        $today = Calendar::for($org)->today()->toDateString();
        foreach (AssessmentCycle::where('status', 'draft')->where('opens_on', '<=', $today)->get() as $c) {
            $this->open($c);
        }
        AssessmentCycle::where('status', 'open')->where('closes_on', '<', $today)->update(['status' => 'closed']);
    }

    public function open(AssessmentCycle $c): void
    {
        DB::transaction(function () use ($c) {
            $c->status = 'open';
            $c->save();
            $cal = Calendar::for($this->tenant->organization);
            $start = CarbonImmutable::parse(max($c->opens_on->toDateString(), $cal->today()->toDateString()), $cal->timezone);
            $e = new Event(['title' => $c->name, 'type' => 'assessment', 'scope' => 'org', 'all_day' => true,
                'starts_at' => $start->utc(), 'ends_at' => CarbonImmutable::parse($c->closes_on->toDateString(), $cal->timezone)->endOfDay()->utc()]);
            // Opened by the scheduler during some member's request; that member is recorded as the creator.
            $e->created_by = $this->access->id() ?? 0;
            $e->save();
        });
    }

    /** LA-02: self, parent seat holders, sibling seat holders in the same team, direct child seat holders. */
    public function defaultAssessors(int $subject): array
    {
        $f = $this->access->formation();
        $ids = [$subject];
        foreach ($f->seatIdsHeldBy($subject) as $sid) {
            $parent = $f->seat($sid)->parent_id;
            if ($parent) {
                $ids[] = $f->seat($parent)->holder_id;
                foreach ($f->childIds($parent) as $sib) {
                    $ids[] = $f->seat($sib)->holder_id;
                }
            }
            foreach ($f->childIds($sid) as $c) {
                $ids[] = $f->seat($c)->holder_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function assign(AssessmentCycle $c, int $subject, int $type): AssessmentAssignment
    {
        $a = $c->assignments()->firstOrCreate(['subject_id' => $subject, 'type_id' => $type]);
        if ($a->wasRecentlyCreated) {
            foreach ($this->defaultAssessors($subject) as $id) {
                $a->assessors()->create(['assessor_id' => $id, 'is_self' => $id === $subject]);
            }
        }

        return $a;
    }

    public static function hasAnswers(AssessmentAssignment $a): bool
    {
        return DB::table('assessment_answers')->where('assignment_id', $a->id)->exists();
    }

    /**
     * LA-05 for one subject in one closed cycle. Item score = mean of non-self answers; type = mean of items;
     * overall = mean of types; self shown separately.
     */
    public function results(AssessmentCycle $c, int $subject): array
    {
        $assignments = AssessmentAssignment::with(['type.items'])->where('cycle_id', $c->id)->where('subject_id', $subject)->get();
        $types = [];
        foreach ($assignments as $a) {
            $answers = DB::table('assessment_answers')->where('assignment_id', $a->id)->get();
            $items = [];
            foreach ($a->type->items as $item) {
                $rows = $answers->where('item_id', $item->id);
                $others = $rows->where('is_self', false)->pluck('score')->map(fn ($s) => (float) $s);
                $self = $rows->where('is_self', true)->pluck('score')->map(fn ($s) => (float) $s);
                if ($others->isEmpty() && $self->isEmpty()) {
                    continue;
                }
                $items[] = ['text' => $item->text, 'others' => $others->isEmpty() ? null : round($others->avg(), 2), 'self' => $self->isEmpty() ? null : round($self->avg(), 2)];
            }
            $oth = collect($items)->pluck('others')->filter(fn ($v) => $v !== null);
            $slf = collect($items)->pluck('self')->filter(fn ($v) => $v !== null);
            $types[] = [
                'name' => $a->type->name, 'type_id' => $a->type_id, 'items' => $items,
                'others' => $oth->isEmpty() ? null : round($oth->avg(), 2), 'self' => $slf->isEmpty() ? null : round($slf->avg(), 2),
                'assessors' => $a->assessors()->where('is_self', false)->whereNotNull('completed_at')->count(),
            ];
        }
        $ov = collect($types)->pluck('others')->filter(fn ($v) => $v !== null);
        $os = collect($types)->pluck('self')->filter(fn ($v) => $v !== null);

        return ['types' => $types, 'overall' => $ov->isEmpty() ? null : round($ov->avg(), 2), 'self' => $os->isEmpty() ? null : round($os->avg(), 2)];
    }
}
