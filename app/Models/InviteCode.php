<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'created_by',
        'used_by',
        'used_at',
        'expires_at',
        'max_uses',
        'current_uses',
        'is_active',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'current_uses' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
