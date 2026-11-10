<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['level', 'team_seat_id', 'parent_id', 'title', 'done_definition', 'quarter_start'];

    protected function casts(): array
    {
        return ['quarter_start' => 'date', 'status_updated_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Mission::class, 'parent_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(MissionMilestone::class)->orderBy('sort_order');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['on_track', 'off_track'], true);
    }
}
