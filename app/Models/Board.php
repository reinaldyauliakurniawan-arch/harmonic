<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['title', 'team_seat_id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('sort_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
