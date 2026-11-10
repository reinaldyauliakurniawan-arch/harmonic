<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = ['subject_id', 'type_id'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AssessmentCycle::class, 'cycle_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'subject_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class, 'type_id');
    }

    public function assessors(): HasMany
    {
        return $this->hasMany(AssessmentAssessor::class, 'assignment_id');
    }
}
