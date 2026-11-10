<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentCycle extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'opens_on', 'closes_on'];

    protected function casts(): array
    {
        return ['opens_on' => 'date', 'closes_on' => 'date'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class, 'cycle_id');
    }
}
