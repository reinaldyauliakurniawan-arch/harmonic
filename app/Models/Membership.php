<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_org_admin' => 'boolean', 'joined_at' => 'datetime', 'departed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'holder_id');
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? 'Former member';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(count($parts) > 1 ? end($parts) : '', 0, 1));
    }

    public function scopeActive($q)
    {
        return $q->where('memberships.status', 'active');
    }
}
