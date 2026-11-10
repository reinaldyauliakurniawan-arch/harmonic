<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class DiagnosticCycle extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closes_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
