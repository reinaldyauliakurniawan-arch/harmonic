<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Problem extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['team_seat_id', 'title', 'description', 'horizon'];

    protected function casts(): array
    {
        return ['solved_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'owner_id');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'raised_by');
    }

    public function solve(): HasOne
    {
        return $this->hasOne(SolveRecord::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'solving'], true);
    }
}
