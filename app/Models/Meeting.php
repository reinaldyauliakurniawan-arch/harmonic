<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['team_seat_id', 'type', 'event_id', 'scheduled_for'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'segment_started_at' => 'datetime'];
    }

    public function headlines(): HasMany
    {
        return $this->hasMany(MeetingHeadline::class);
    }

    public function isLive(): bool
    {
        return $this->started_at && ! $this->ended_at;
    }
}
