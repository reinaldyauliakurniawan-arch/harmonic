<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $fillable = ['title', 'output', 'definition_of_done', 'due_date', 'team_seat_id', 'board_id', 'board_column_id', 'position'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'responsible_id');
    }

    public function accountable(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'accountable_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TaskStep::class)->orderBy('sort_order');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'task_people', 'task_id', 'member_id')->withPivot('role');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['todo', 'doing'], true);
    }
}
