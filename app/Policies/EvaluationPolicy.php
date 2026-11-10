<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Tenant;

class EvaluationPolicy
{
    public function __construct(private Access $a, private Tenant $t) {}

    /** E-01: anyone holding an ancestor seat of the evaluated seat. */
    public function create(User $u, int $seatId): bool
    {
        return $this->a->above($seatId);
    }

    /** Subject, holders of any ancestor seat, Org Admin (section 5 visibility). */
    public function viewPerson(User $u, int $subjectId): bool
    {
        return $this->a->seesPerson($subjectId);
    }

    /** E-04: the author, until the end of the day it was created in the organization's timezone. */
    public function update(User $u, Evaluation $e): bool
    {
        $cal = Calendar::for($this->t->organization);

        return $this->a->owner($e->evaluator_id) && $cal->local($e->created_at)->toDateString() === $cal->today()->toDateString();
    }
}
