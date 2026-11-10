<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SchedulerRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** The lazy scheduler (PRD section 4): runs due jobs on ordinary requests instead of cron. */
class Scheduler
{
    public const EVERY_MINUTES = 10;

    /** @return array<string, callable(Organization): void> */
    public function jobs(): array
    {
        return [
            'alignment.close' => fn ($org) => app(Jobs::class)->closeAlignmentRounds(),
            'diagnostic.close' => fn ($org) => app(Jobs::class)->closeDiagnosticCycles(),
            'assessment.cycle' => fn ($org) => app(Jobs::class)->advanceAssessmentCycles($org),
            'calendar.extend' => fn ($org) => app(Jobs::class)->extendSeries($org),
        ];
    }

    public function runIfDue(Organization $org): void
    {
        $lock = Cache::lock('scheduler:'.$org->id, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            foreach ($this->jobs() as $job => $run) {
                $this->runJob($org->id, $job, fn () => $run($org));
            }
            $this->runJob(null, 'org.purge', fn () => app(Jobs::class)->purgeOrganizations());
        } finally {
            $lock->release();
        }
    }

    private function runJob(?int $orgId, string $job, callable $run): void
    {
        $last = SchedulerRun::where('organization_id', $orgId)->where('job', $job)->value('last_run_at');
        if ($last && now()->diffInMinutes($last, true) < self::EVERY_MINUTES) {
            return;
        }
        try {
            DB::transaction(fn () => $run());
        } catch (Throwable $e) {
            report($e);
        }
        SchedulerRun::updateOrCreate(['organization_id' => $orgId, 'job' => $job], ['last_run_at' => now()]);
    }
}
