<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['title', 'purpose', 'body', 'team_seat_id', 'owner_seat_id', 'audience', 'review_every_months'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reviewed_at' => 'date', 'archived_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProcedureVersion::class)->orderByDesc('version');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SyncAudit::class)->orderByDesc('audited_on')->orderByDesc('id');
    }

    public function ownerSeat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'owner_seat_id');
    }

    public function reviewDue(): ?CarbonInterface
    {
        return $this->reviewed_at?->copy()->addMonths($this->review_every_months);
    }
}
