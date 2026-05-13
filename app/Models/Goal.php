<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'current_amount',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'float',
            'current_amount' => 'float',
            'deadline' => 'date',
        ];
    }
}
