<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolveRecord extends Model
{
    protected $fillable = ['ignite', 'statement', 'observe', 'leverage', 'solution', 'evaluate_on', 'evaluation', 'evaluation_result'];

    protected function casts(): array
    {
        return ['evaluate_on' => 'date'];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }
}
