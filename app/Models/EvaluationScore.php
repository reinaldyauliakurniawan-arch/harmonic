<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationScore extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'evaluation_id';

    protected $fillable = ['belief_id', 'score'];
}
