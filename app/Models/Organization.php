<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'timezone', 'week_start_day', 'quarter_start_date', 'evaluator_bar_max_mixed', 'evaluator_bar_max_minus'];

    protected function casts(): array
    {
        return [
            'quarter_start_date' => 'date',
            'approved_at' => 'datetime',
            'purge_after' => 'date',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScope('organization');
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'org', 70, '');
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
