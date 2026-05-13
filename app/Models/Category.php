<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'name_fa',
        'icon',
        'type',
        'is_default',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
