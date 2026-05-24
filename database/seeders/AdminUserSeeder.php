<?php

namespace Database\Seeders;

use App\Models\OnboardingProfile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OnboardingService;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $authService = app(AuthService::class);
        $onboardingService = app(OnboardingService::class);

        $email = $authService->normalizeEmail(env('ADMIN_EMAIL', 'royyapardazesh@gmail.com'));
        $password = env('ADMIN_PASSWORD', 'Danial#369');
        $salt = $authService->generateSalt();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'password_salt' => $salt,
                'password_hash' => $authService->hashPassword($password, $salt),
                'first_name' => env('ADMIN_FIRST_NAME', 'Admin'),
                'last_name' => env('ADMIN_LAST_NAME', 'User'),
                'phone_number' => env('ADMIN_PHONE_NUMBER', '0000000000'),
                'personal_info_completed_at' => now(),
                'is_admin' => true,
            ],
        );

        OnboardingProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'investment_experience' => 'advanced',
                'market_decline_reaction' => 'do_nothing',
                'investment_horizon' => 'long',
                'monthly_income_range' => 'over_200M',
                'saving_habit' => 'over_30',
                'financial_anxiety' => 'not_at_all',
                'risk_score' => 72,
                'risk_level' => 'growth',
                'discipline_score' => 100,
                'stress_level' => 'low',
                'time_horizon_level' => 'long',
                'income_capacity_level' => 'high',
                'confidence' => 95,
                'recommendations_json' => $onboardingService->serializeRecommendations([
                    'portfolio_suggestions' => [],
                    'financial_health_actions' => ['Review platform data and user feedback regularly.'],
                    'explanation' => 'Seeded profile for the default administrator account.',
                ]),
                'is_completed' => true,
                'last_completed_step' => 6,
            ],
        );
    }
}
