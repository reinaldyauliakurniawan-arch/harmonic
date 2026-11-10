<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = ['title', 'responsibilities', 'team_name', 'sort_order'];

    protected function casts(): array
    {
        return ['responsibilities' => 'array', 'is_leadership_team' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Seat::class, 'parent_id')->orderBy('sort_order');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'holder_id');
    }

    public function teamLabel(): string
    {
        return $this->team_name ?: $this->title.' team';
    }
}
