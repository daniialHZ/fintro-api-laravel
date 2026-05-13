<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnomalyAnalytics extends Model
{
    public $timestamps = false;

    protected $table = 'anomaly_analytics';

    protected $fillable = [
        'user_id',
        'analysis_date',
        'anomalies_detected',
        'has_anomalies',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'analysis_date' => 'date',
            'has_anomalies' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
