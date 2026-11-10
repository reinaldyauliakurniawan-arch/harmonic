<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use BelongsToOrganization;

    protected $table = 'activity_log';

    public const UPDATED_AT = null;

    protected $fillable = ['action', 'subject_type', 'subject_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'actor_id');
    }

    public static function record(string $action, ?Model $subject = null, array $data = []): self
    {
        $log = new self(['action' => $action, 'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(), 'data' => $data]);
        $log->actor_id = app(Tenant::class)->membership?->id;
        $log->save();

        return $log;
    }
}
