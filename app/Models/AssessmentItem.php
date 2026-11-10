<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['text', 'sort_order'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
