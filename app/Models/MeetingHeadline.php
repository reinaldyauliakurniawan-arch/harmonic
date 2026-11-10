<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingHeadline extends Model
{
    public $timestamps = false;

    protected $fillable = ['kind', 'text'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'member_id');
    }
}
