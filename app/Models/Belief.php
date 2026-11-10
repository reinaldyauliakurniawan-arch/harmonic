<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class Belief extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['name', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
