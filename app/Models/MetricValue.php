<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricValue extends Model
{
    protected $fillable = ['week_start', 'value', 'note'];

    protected function casts(): array
    {
        return ['week_start' => 'date', 'value' => 'float', 'goal_value' => 'float', 'goal_value_max' => 'float'];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
