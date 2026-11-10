<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['understands', 'wants', 'capacity', 'notes'];

    protected function casts(): array
    {
        return ['evaluated_on' => 'date', 'understands' => 'boolean', 'wants' => 'boolean', 'capacity' => 'boolean'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'subject_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'evaluator_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
