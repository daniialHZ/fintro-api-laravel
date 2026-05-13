<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingProfile extends Model
{
    protected $fillable = [
        'user_id',
        'investment_experience',
        'market_decline_reaction',
        'investment_horizon',
        'monthly_income_range',
        'saving_habit',
        'financial_anxiety',
        'risk_score',
        'risk_level',
        'discipline_score',
        'stress_level',
        'time_horizon_level',
        'income_capacity_level',
        'confidence',
        'recommendations_json',
        'is_completed',
        'last_completed_step',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'float',
            'discipline_score' => 'float',
            'confidence' => 'float',
            'is_completed' => 'boolean',
            'last_completed_step' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
