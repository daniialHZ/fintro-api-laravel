<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthAnalytics extends Model
{
    public $timestamps = false;

    protected $table = 'health_analytics';

    protected $fillable = [
        'user_id',
        'analysis_date',
        'financial_health_score',
        'analysis_text',
        'recommendations',
        'summary',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'analysis_date' => 'date',
            'financial_health_score' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
