<?php

namespace App\Http\Controllers\Api;

use App\Models\OnboardingProfile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\InviteService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly InviteService $inviteService,
        private readonly OnboardingService $onboardingService,
    ) {
    }

    public function signup(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone_number' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9۰-۹+\-\s()]+$/u'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'invite_code' => ['required', 'string', 'min:4', 'max:50'],
            'onboarding_answers.investment_experience' => ['required', 'in:none,basic,intermediate,advanced'],
            'onboarding_answers.market_decline_reaction' => ['required', 'in:sell_all,sell_some,do_nothing,buy_more'],
            'onboarding_answers.investment_horizon' => ['required', 'in:short,medium,long,very_long'],
            'onboarding_answers.monthly_income_range' => ['nullable', 'in:under_10M,10M_30M,30M_50M,50M_100M,100M_200M,over_200M'],
            'onboarding_answers.saving_habit' => ['required', 'in:none,less_10,10_20,20_30,over_30'],
            'onboarding_answers.financial_anxiety' => ['required', 'in:very_anxious,somewhat,little,not_at_all'],
        ]);

        $invite = $this->inviteService->validateInviteCode($payload['invite_code']);
        if (! $invite['valid']) {
            return response()->json(['detail' => $invite['message']], 403);
        }

        $email = $this->authService->normalizeEmail($payload['email']);
        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['detail' => 'Email already exists'], 400);
        }

        $salt = $this->authService->generateSalt();
        $token = $this->authService->generateAuthToken();
        $answers = $payload['onboarding_answers'];
        [$profileData, $recommendations] = $this->onboardingService->buildProfileAndRecommendations($answers);

        $user = User::query()->create([
            'email' => $email,
            'first_name' => trim($payload['first_name']),
            'last_name' => trim($payload['last_name']),
            'phone_number' => trim($payload['phone_number']),
            'personal_info_completed_at' => now(),
            'password_salt' => $salt,
            'password_hash' => $this->authService->hashPassword($payload['password'], $salt),
            'auth_token' => $token,
        ]);

        $this->inviteService->useInviteCode($payload['invite_code'], $user->id);

        OnboardingProfile::query()->create([
            'user_id' => $user->id,
            'investment_experience' => $answers['investment_experience'],
            'market_decline_reaction' => $answers['market_decline_reaction'],
            'investment_horizon' => $answers['investment_horizon'],
            'monthly_income_range' => $answers['monthly_income_range'] ?? null,
            'saving_habit' => $answers['saving_habit'],
            'financial_anxiety' => $answers['financial_anxiety'],
            'risk_score' => $profileData['risk_score'],
            'risk_level' => $profileData['risk_level'],
            'discipline_score' => $profileData['discipline_score'],
            'stress_level' => $profileData['stress_level'],
            'time_horizon_level' => $profileData['time_horizon_level'],
            'income_capacity_level' => $profileData['income_capacity_level'],
            'confidence' => $profileData['confidence'],
            'recommendations_json' => $this->onboardingService->serializeRecommendations($recommendations),
            'is_completed' => true,
            'last_completed_step' => 6,
        ]);

        return response()->json([
            'token' => $token,
            'user' => $this->authUserPayload($user),
            'profile' => $profileData,
            'recommendations' => $recommendations,
        ], 201);
    }

    public function signin(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $email = $this->authService->normalizeEmail($payload['email']);
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $this->authService->verifyPassword($payload['password'], $user->password_salt, $user->password_hash)) {
            return response()->json(['detail' => 'Invalid credentials'], 401);
        }

        $profile = OnboardingProfile::query()->where('user_id', $user->id)->first();
        if (! $profile) {
            return response()->json(['detail' => 'No onboarding profile found for this user'], 400);
        }

        $user->auth_token = $this->authService->generateAuthToken();
        $user->save();

        if (! $profile->recommendations_json) {
            throw ValidationException::withMessages(['profile' => 'User profile is incomplete']);
        }

        return response()->json([
            'token' => $user->auth_token,
            'user' => $this->authUserPayload($user),
            'profile' => [
                'risk_score' => (int) round((float) ($profile->risk_score ?? 0)),
                'risk_level' => $profile->risk_level,
                'discipline_score' => (int) round((float) ($profile->discipline_score ?? 0)),
                'stress_level' => $profile->stress_level,
                'time_horizon_level' => $profile->time_horizon_level,
                'income_capacity_level' => $profile->income_capacity_level,
                'confidence' => (int) round((float) ($profile->confidence ?? 0)),
            ],
            'recommendations' => $this->onboardingService->deserializeRecommendations($profile->recommendations_json),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->authUserPayload($this->currentUser($request)));
    }
}
