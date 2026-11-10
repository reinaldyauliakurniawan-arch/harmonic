<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class RubricLevel extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = ['score', 'label', 'description'];
}
