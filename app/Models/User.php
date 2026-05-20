<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password_salt',
        'password_hash',
        'auth_token',
        'is_admin',
        'last_seen_at',
    ];

    protected $hidden = [
        'password_salt',
        'password_hash',
        'auth_token',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'is_admin' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function onboardingProfile()
    {
        return $this->hasOne(OnboardingProfile::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function wealthItems(): HasMany
    {
        return $this->hasMany(Wealth::class);
    }

    public function portfolioTargets(): HasMany
    {
        return $this->hasMany(PortfolioTarget::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(UserSuggestion::class);
    }
}
