<?php

namespace App\Http\Controllers\Api;

use App\Models\OnboardingProfile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly OnboardingService $onboardingService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $profile = OnboardingProfile::query()->where('user_id', $user->id)->first();
        if (! $profile) {
            return response()->json(['detail' => 'Profile not found. Please complete onboarding.'], 404);
        }

        $recommendations = $this->onboardingService->deserializeRecommendations($profile->recommendations_json);

        return response()->json([
            'user' => $this->authUserPayload($user),
            'profile' => [
                'risk_score' => (int) round((float) ($profile->risk_score ?? 0)),
                'risk_level' => $profile->risk_level ?: 'moderate',
                'discipline_score' => (int) round((float) ($profile->discipline_score ?? 0)),
                'stress_level' => $profile->stress_level ?: 'medium',
                'time_horizon_level' => $profile->time_horizon_level ?: 'medium',
                'income_capacity_level' => $profile->income_capacity_level ?: 'medium',
                'confidence' => (int) round((float) ($profile->confidence ?? 0)),
            ],
            'recommendations' => $recommendations['financial_health_actions'] ?? [],
        ]);
    }

    public function updateEmail(Request $request): JsonResponse
    {
        $payload = $request->validate(['email' => ['required', 'string', 'email']]);
        $user = $this->currentUser($request);
        $email = $this->authService->normalizeEmail($payload['email']);

        $exists = User::query()
            ->where('email', $email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['detail' => 'Email already in use by another account'], 400);
        }

        $user->email = $email;
        $user->save();

        return response()->json(['message' => 'Email updated successfully', 'email' => $email]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user = $this->currentUser($request);
        if (! $this->authService->verifyPassword($payload['current_password'], $user->password_salt, $user->password_hash)) {
            return response()->json(['detail' => 'Current password is incorrect'], 401);
        }

        $salt = $this->authService->generateSalt();
        $user->password_salt = $salt;
        $user->password_hash = $this->authService->hashPassword($payload['new_password'], $salt);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function refreshRecommendations(Request $request): JsonResponse
    {
        $profile = OnboardingProfile::query()->where('user_id', $this->currentUser($request)->id)->first();
        if (! $profile) {
            return response()->json(['detail' => 'Profile not found'], 404);
        }

        [$profileData, $recommendations] = $this->onboardingService->buildProfileAndRecommendations([
            'investment_experience' => $profile->investment_experience,
            'market_decline_reaction' => $profile->market_decline_reaction,
            'investment_horizon' => $profile->investment_horizon,
            'monthly_income_range' => $profile->monthly_income_range,
            'saving_habit' => $profile->saving_habit,
            'financial_anxiety' => $profile->financial_anxiety,
        ]);

        $profile->fill([
            'risk_score' => $profileData['risk_score'],
            'risk_level' => $profileData['risk_level'],
            'discipline_score' => $profileData['discipline_score'],
            'stress_level' => $profileData['stress_level'],
            'time_horizon_level' => $profileData['time_horizon_level'],
            'income_capacity_level' => $profileData['income_capacity_level'],
            'confidence' => $profileData['confidence'],
            'recommendations_json' => $this->onboardingService->serializeRecommendations($recommendations),
        ]);
        $profile->save();

        return response()->json([
            'message' => 'Recommendations refreshed successfully',
            'recommendations' => $recommendations['financial_health_actions'] ?? [],
        ]);
    }

    public function debug(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'has_profile' => true,
        ]);
    }
}
