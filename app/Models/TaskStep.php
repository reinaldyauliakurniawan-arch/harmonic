<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStep extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'sort_order'];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }
}
