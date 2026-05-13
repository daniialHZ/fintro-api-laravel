<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioTarget extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'title',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'float',
        ];
    }
}
