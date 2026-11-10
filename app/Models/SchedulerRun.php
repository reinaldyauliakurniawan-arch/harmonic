<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'job', 'last_run_at'];

    protected function casts(): array
    {
        return ['last_run_at' => 'datetime'];
    }
}
