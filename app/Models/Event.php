<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['title', 'starts_at', 'ends_at', 'all_day', 'notes', 'scope', 'type', 'team_seat_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'created_by');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'partner_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'series_id');
    }

    /** Private events only for the creator, pair events only for the two participants (PRD C-06). */
    public function scopeVisibleTo($q, Membership $m)
    {
        return $q->where(function ($q) use ($m) {
            $q->whereIn('scope', ['org', 'team'])
                ->orWhere(fn ($q) => $q->where('scope', 'private')->where('created_by', $m->id))
                ->orWhere(fn ($q) => $q->where('scope', 'pair')->where(fn ($q) => $q->where('created_by', $m->id)->orWhere('partner_id', $m->id)));
        });
    }
}
