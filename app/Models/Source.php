<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'name_fa',
        'icon',
        'is_default',
        'is_custom',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_custom' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
