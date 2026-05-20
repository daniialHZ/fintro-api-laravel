<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingProfile;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    protected function currentUser(Request $request): User
    {
        return $request->attributes->get('current_user');
    }

    protected function authUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'last_seen_at' => $user->last_seen_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    protected function onboardingResponse(OnboardingProfile $profile, OnboardingService $service): array
    {
        if (! $profile->is_completed || ! $profile->recommendations_json) {
            return ['exists' => true, 'data' => null];
        }

        $required = [
            'investment_experience',
            'market_decline_reaction',
            'investment_horizon',
            'saving_habit',
            'financial_anxiety',
            'risk_score',
            'risk_level',
            'discipline_score',
            'stress_level',
            'time_horizon_level',
            'income_capacity_level',
            'confidence',
        ];

        foreach ($required as $field) {
            if ($profile->{$field} === null) {
                return ['exists' => true, 'data' => null];
            }
        }

        return [
            'exists' => true,
            'data' => [
                'answers' => [
                    'investment_experience' => $profile->investment_experience,
                    'market_decline_reaction' => $profile->market_decline_reaction,
                    'investment_horizon' => $profile->investment_horizon,
                    'monthly_income_range' => $profile->monthly_income_range,
                    'saving_habit' => $profile->saving_habit,
                    'financial_anxiety' => $profile->financial_anxiety,
                ],
                'profile' => [
                    'risk_score' => (int) round((float) $profile->risk_score),
                    'risk_level' => $profile->risk_level,
                    'discipline_score' => (int) round((float) $profile->discipline_score),
                    'stress_level' => $profile->stress_level,
                    'time_horizon_level' => $profile->time_horizon_level,
                    'income_capacity_level' => $profile->income_capacity_level,
                    'confidence' => (int) round((float) $profile->confidence),
                ],
                'recommendations' => $service->deserializeRecommendations($profile->recommendations_json),
                'is_completed' => (bool) $profile->is_completed,
                'last_completed_step' => (int) $profile->last_completed_step,
                'updated_at' => $profile->updated_at?->toISOString(),
            ],
        ];
    }
}
