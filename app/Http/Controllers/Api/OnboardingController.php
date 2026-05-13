<?php

namespace App\Http\Controllers\Api;

use App\Models\OnboardingProfile;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends BaseApiController
{
    public function __construct(private readonly OnboardingService $onboardingService)
    {
    }

    public function submit(Request $request): JsonResponse
    {
        $answers = $this->validateAnswers($request, true);
        $profile = $this->getOrCreateProfile($this->currentUser($request)->id);
        [$profileData, $recommendations] = $this->onboardingService->buildProfileAndRecommendations($answers);

        $profile->fill([
            ...$answers,
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
        $profile->save();

        return response()->json($this->onboardingResponse($profile, $this->onboardingService));
    }

    public function profile(Request $request): JsonResponse
    {
        $profile = OnboardingProfile::query()->where('user_id', $this->currentUser($request)->id)->first();
        if (! $profile) {
            return response()->json(['exists' => false, 'data' => null]);
        }

        return response()->json($this->onboardingResponse($profile, $this->onboardingService));
    }

    public function patchAnswers(Request $request): JsonResponse
    {
        $payload = $this->validateAnswers($request, false);
        if (empty($payload)) {
            return response()->json(['detail' => 'At least one field must be provided for update'], 422);
        }

        $profile = $this->getOrCreateProfile($this->currentUser($request)->id);

        foreach ($payload as $field => $value) {
            $profile->{$field} = $value;
        }

        $requiredReady = $profile->investment_experience
            && $profile->market_decline_reaction
            && $profile->investment_horizon
            && $profile->saving_habit
            && $profile->financial_anxiety;

        if ($requiredReady) {
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
                'is_completed' => true,
                'last_completed_step' => max((int) $profile->last_completed_step, 6),
            ]);
        } else {
            $profile->is_completed = false;
        }

        $profile->save();

        return response()->json($this->onboardingResponse($profile, $this->onboardingService));
    }

    private function getOrCreateProfile(int $userId): OnboardingProfile
    {
        return OnboardingProfile::query()->firstOrCreate(['user_id' => $userId]);
    }

    private function validateAnswers(Request $request, bool $required): array
    {
        $prefix = $required ? 'required' : 'nullable';

        return $request->validate([
            'investment_experience' => [$prefix, 'in:none,basic,intermediate,advanced'],
            'market_decline_reaction' => [$prefix, 'in:sell_all,sell_some,do_nothing,buy_more'],
            'investment_horizon' => [$prefix, 'in:short,medium,long,very_long'],
            'monthly_income_range' => ['nullable', 'in:under_10M,10M_30M,30M_50M,50M_100M,100M_200M,over_200M'],
            'saving_habit' => [$prefix, 'in:none,less_10,10_20,20_30,over_30'],
            'financial_anxiety' => [$prefix, 'in:very_anxious,somewhat,little,not_at_all'],
            'last_completed_step' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);
    }
}
