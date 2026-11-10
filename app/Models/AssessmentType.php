<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentType extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class, 'type_id')->orderBy('sort_order');
    }
}
