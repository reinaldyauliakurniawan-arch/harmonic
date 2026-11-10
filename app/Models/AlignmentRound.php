<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlignmentRound extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closes_at' => 'datetime', 'closed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function fundamentals(): BelongsTo
    {
        return $this->belongsTo(Fundamental::class, 'fundamentals_id');
    }

    public function isOpen(): bool
    {
        return ! $this->closed_at;
    }
}
