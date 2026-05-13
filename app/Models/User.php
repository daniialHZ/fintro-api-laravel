<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password_salt',
        'password_hash',
        'auth_token',
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
        ];
    }

    public function onboardingProfile()
    {
        return $this->hasOne(OnboardingProfile::class);
    }
}
