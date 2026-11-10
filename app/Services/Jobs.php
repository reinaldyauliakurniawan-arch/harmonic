<?php

namespace App\Services;

use App\Models\AlignmentRound;
use App\Models\DiagnosticCycle;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Jobs the lazy scheduler runs. Each is safe to run twice. */
class Jobs
{
    public function closeAlignmentRounds(): void
    {
        AlignmentRound::whereNull('closed_at')->where('closes_at', '<=', now())->update(['closed_at' => now()]);
    }

    public function closeDiagnosticCycles(): void
    {
        DiagnosticCycle::whereNull('closed_at')->where('closes_at', '<=', now())->update(['closed_at' => now()]);
    }

    public function advanceAssessmentCycles(Organization $org): void
    {
        app(Assessments::class)->advance($org);
    }

    public function extendSeries(Organization $org): void
    {
        app(Recurrence::class)->extendAll();
    }

    public function purgeOrganizations(): void
    {
        Organization::where('status', 'deleted')->whereDate('purge_after', '<', now()->toDateString())
            ->get()->each(fn ($org) => $this->purge($org));
    }

    /** Removes every row of an organization. Children first, then tenant tables, then the organization. */
    public function purge(Organization $org): void
    {
        $id = $org->id;
        $children = [
            'board_columns' => ['board_id', 'boards'],
            'task_people' => ['task_id', 'tasks'],
            'task_steps' => ['task_id', 'tasks'],
            'metric_values' => ['metric_id', 'metrics'],
            'mission_milestones' => ['mission_id', 'missions'],
            'meeting_attendance' => ['meeting_id', 'meetings'],
            'meeting_ratings' => ['meeting_id', 'meetings'],
            'meeting_headlines' => ['meeting_id', 'meetings'],
            'solve_records' => ['problem_id', 'problems'],
            'alignment_completions' => ['round_id', 'alignment_rounds'],
            'alignment_answers' => ['round_id', 'alignment_rounds'],
            'procedure_versions' => ['procedure_id', 'procedures'],
            'procedure_acks' => ['procedure_id', 'procedures'],
            'sync_audits' => ['procedure_id', 'procedures'],
            'evaluation_scores' => ['evaluation_id', 'evaluations'],
            'diagnostic_completions' => ['cycle_id', 'diagnostic_cycles'],
            'diagnostic_answers' => ['cycle_id', 'diagnostic_cycles'],
            'assessment_items' => ['type_id', 'assessment_types'],
        ];
        $tenant = ['activity_log', 'invitations', 'scheduler_runs', 'boards', 'tasks', 'events', 'event_series', 'metrics', 'missions',
            'meetings', 'problems', 'beliefs', 'alignment_rounds', 'fundamentals', 'procedures', 'evaluations', 'diagnostic_cycles',
            'rubric_levels', 'seats', 'memberships'];

        DB::transaction(function () use ($id, $children, $tenant, $org) {
            Schema::disableForeignKeyConstraints();
            try {
                $assignments = DB::table('assessment_assignments')->whereIn('cycle_id', DB::table('assessment_cycles')->where('organization_id', $id)->select('id'))->pluck('id');
                DB::table('assessment_answers')->whereIn('assignment_id', $assignments)->delete();
                DB::table('assessment_assessors')->whereIn('assignment_id', $assignments)->delete();
                DB::table('assessment_assignments')->whereIn('id', $assignments)->delete();
                DB::table('assessment_cycles')->where('organization_id', $id)->delete();
                foreach ($children as $table => [$fk, $parent]) {
                    DB::table($table)->whereIn($fk, DB::table($parent)->where('organization_id', $id)->select('id'))->delete();
                }
                DB::table('assessment_types')->where('organization_id', $id)->delete();
                foreach ($tenant as $table) {
                    DB::table($table)->where('organization_id', $id)->delete();
                }
                $org->delete();
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });
    }
}
