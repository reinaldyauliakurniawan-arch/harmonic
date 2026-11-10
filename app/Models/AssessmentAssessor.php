<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAssessor extends Model
{
    public $timestamps = false;

    protected $fillable = ['assessor_id', 'is_self'];

    protected function casts(): array
    {
        return ['is_self' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssessmentAssignment::class, 'assignment_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'assessor_id');
    }
}
