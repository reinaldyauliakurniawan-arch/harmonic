<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionMilestone extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'due_date', 'sort_order'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'done_at' => 'datetime'];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
