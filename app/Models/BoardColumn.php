<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardColumn extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'is_done_column', 'sort_order'];

    protected function casts(): array
    {
        return ['is_done_column' => 'boolean'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
