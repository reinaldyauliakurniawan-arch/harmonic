<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Metric extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'description', 'team_seat_id', 'owner_seat_id', 'unit', 'decimals', 'operator', 'goal_value', 'goal_value_max', 'quarter_rollup', 'auto_problem_after'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'goal_value' => 'float', 'goal_value_max' => 'float'];
    }

    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class);
    }

    public function ownerSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'owner_seat_id');
    }

    public function teamSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'team_seat_id');
    }
}
