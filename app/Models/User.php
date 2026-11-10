<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'sessions_valid_from' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScope('organization');
    }

    /** Signs out every existing session of this user; the current request keeps its own when it is theirs. */
    public function invalidateSessions(): void
    {
        $this->forceFill(['sessions_valid_from' => now()->addSecond()])->save();
        if (auth()->id() === $this->id && request()->hasSession()) {
            request()->session()->put('auth_at', now()->addSeconds(2)->timestamp);
        }
    }

    /** Active memberships in active organizations, with the organization loaded. */
    public function activeMemberships()
    {
        return $this->memberships()->where('status', 'active')
            ->whereHas('organization', fn ($q) => $q->where('status', 'active'))
            ->with('organization')->get();
    }
}
