<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncAudit extends Model
{
    protected $fillable = ['audited_on', 'result', 'findings'];

    protected function casts(): array
    {
        return ['audited_on' => 'date'];
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'auditor_id');
    }
}
