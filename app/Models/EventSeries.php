<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeries extends Model
{
    use BelongsToOrganization;

    protected $table = 'event_series';

    protected $fillable = ['rule', 'until'];

    protected function casts(): array
    {
        return ['until' => 'date'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id');
    }
}
