<?php

namespace App\Services;

class OnboardingService
{
    private const EXPERIENCE_SCORES = [
        'none' => 1,
        'basic' => 2,
        'intermediate' => 3,
        'advanced' => 4,
    ];

    private const DECLINE_REACTION_SCORES = [
        'sell_all' => 1,
        'sell_some' => 2,
        'do_nothing' => 3,
        'buy_more' => 4,
    ];

    private const HORIZON_SCORES = [
        'short' => 1,
        'medium' => 2,
        'long' => 3,
        'very_long' => 4,
    ];

    private const SAVING_SCORES = [
        'none' => 1,
        'less_10' => 2,
        '10_20' => 3,
        '20_30' => 4,
        'over_30' => 5,
    ];

    private const ANXIETY_SCORES = [
        'very_anxious' => 1,
        'somewhat' => 2,
        'little' => 3,
        'not_at_all' => 4,
    ];

    private const INCOME_LEVEL_MAP = [
        'under_10M' => 'low',
        '10M_30M' => 'low',
        '30M_50M' => 'mid',
        '50M_100M' => 'mid',
        '100M_200M' => 'high',
        'over_200M' => 'high',
    ];

    public function buildProfileAndRecommendations(array $answers): array
    {
        $profile = $this->buildProfile($answers);
        $recommendations = $this->buildRecommendations($answers, $profile);

        return [$profile, $recommendations];
    }

    public function serializeRecommendations(array $recommendations): string
    {
        return json_encode($recommendations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function deserializeRecommendations(?string $recommendationsJson): array
    {
        if (! $recommendationsJson) {
            return [];
        }

        return json_decode($recommendationsJson, true) ?: [];
    }

    private function buildProfile(array $answers): array
    {
        $experienceScore = self::EXPERIENCE_SCORES[$answers['investment_experience']];
        $declineScore = self::DECLINE_REACTION_SCORES[$answers['market_decline_reaction']];
        $horizonScore = self::HORIZON_SCORES[$answers['investment_horizon']];
        $savingScore = self::SAVING_SCORES[$answers['saving_habit']];
        $resilienceScore = self::ANXIETY_SCORES[$answers['financial_anxiety']];

        $riskPrimary = (($experienceScore + $declineScore + $horizonScore) - 3) / 9 * 100;
        $disciplineScore = ($savingScore - 1) / 4 * 100;
        $resilience = ($resilienceScore - 1) / 3 * 100;

        $riskScore = (int) round(($riskPrimary * 0.70) + ($disciplineScore * 0.15) + ($resilience * 0.15));
        $riskScore = max(0, min(100, $riskScore));

        if ($riskScore < 35) {
            $riskLevel = 'conservative';
        } elseif ($riskScore < 55) {
            $riskLevel = 'moderate';
        } elseif ($riskScore < 75) {
            $riskLevel = 'growth';
        } else {
            $riskLevel = 'aggressive';
        }

        $stressLevel = match ($answers['financial_anxiety']) {
            'very_anxious' => 'high',
            'somewhat' => 'medium',
            default => 'low',
        };

        $timeHorizonLevel = match ($answers['investment_horizon']) {
            'short' => 'short',
            'medium' => 'medium',
            default => 'long',
        };

        $incomeCapacityLevel = 'unknown';
        if (! empty($answers['monthly_income_range'])) {
            $incomeCapacityLevel = self::INCOME_LEVEL_MAP[$answers['monthly_income_range']] ?? 'unknown';
        }

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'discipline_score' => (int) round($disciplineScore),
            'stress_level' => $stressLevel,
            'time_horizon_level' => $timeHorizonLevel,
            'income_capacity_level' => $incomeCapacityLevel,
            'confidence' => ! empty($answers['monthly_income_range']) ? 95 : 85,
        ];
    }

    private function buildRecommendations(array $answers, array $profile): array
    {
        $portfolioByRisk = [
            'conservative' => [
                ['title' => 'سپرده بانکی و درآمد ثابت', 'percentage' => 45, 'reasoning' => 'برای کاهش نوسان و حفظ نقدشوندگی مناسب است.', 'risk_level' => 'کم'],
                ['title' => 'صندوق سرمایه گذاری مختلط', 'percentage' => 25, 'reasoning' => 'بازدهی متعادل با ریسک کنترل شده ایجاد می کند.', 'risk_level' => 'متوسط'],
                ['title' => 'طلا', 'percentage' => 20, 'reasoning' => 'برای پوشش تورم و تنوع بخشی مفید است.', 'risk_level' => 'متوسط'],
                ['title' => 'سهام بنیادی', 'percentage' => 10, 'reasoning' => 'برای رشد آرام و تدریجی در بلندمدت.', 'risk_level' => 'متوسط'],
            ],
            'moderate' => [
                ['title' => 'صندوق مختلط', 'percentage' => 35, 'reasoning' => 'تعادل مناسب بین رشد و حفظ سرمایه ایجاد می کند.', 'risk_level' => 'متوسط'],
                ['title' => 'سهام بنیادی', 'percentage' => 30, 'reasoning' => 'برای رشد سرمایه با ریسک قابل مدیریت.', 'risk_level' => 'متوسط'],
                ['title' => 'طلا', 'percentage' => 20, 'reasoning' => 'کاهش اثر نوسانات بازارهای ریسکی.', 'risk_level' => 'متوسط'],
                ['title' => 'نقد و سپرده کوتاه مدت', 'percentage' => 15, 'reasoning' => 'برای فرصت های خرید و مدیریت ریسک نقدینگی.', 'risk_level' => 'کم'],
            ],
            'growth' => [
                ['title' => 'سهام', 'percentage' => 45, 'reasoning' => 'تمرکز اصلی روی رشد سرمایه در افق میان مدت تا بلندمدت.', 'risk_level' => 'زیاد'],
                ['title' => 'صندوق سهامی', 'percentage' => 25, 'reasoning' => 'تنوع در سهام با مدیریت حرفه ای.', 'risk_level' => 'زیاد'],
                ['title' => 'طلا', 'percentage' => 15, 'reasoning' => 'پوشش بخشی از ریسک تورم و عدم قطعیت کلان.', 'risk_level' => 'متوسط'],
                ['title' => 'ارز خارجی', 'percentage' => 10, 'reasoning' => 'تنوع بخشی ارزی در برابر نوسانات اقتصادی.', 'risk_level' => 'متوسط'],
                ['title' => 'نقد', 'percentage' => 5, 'reasoning' => 'انعطاف برای اصلاحات سریع پرتفوی.', 'risk_level' => 'کم'],
            ],
            'aggressive' => [
                ['title' => 'سهام رشدی', 'percentage' => 55, 'reasoning' => 'حداکثر تمرکز روی رشد سرمایه با پذیرش نوسان بالا.', 'risk_level' => 'زیاد'],
                ['title' => 'صندوق سهامی', 'percentage' => 20, 'reasoning' => 'پوشش بخشی از ریسک انتخاب مستقیم سهم.', 'risk_level' => 'زیاد'],
                ['title' => 'دارایی های پرریسک نوآورانه', 'percentage' => 15, 'reasoning' => 'برای ایجاد پتانسیل بازدهی بالاتر در سهم محدود.', 'risk_level' => 'خیلی زیاد'],
                ['title' => 'طلا', 'percentage' => 10, 'reasoning' => 'تثبیت نسبی پرتفوی در شوک های بازار.', 'risk_level' => 'متوسط'],
            ],
        ];

        $healthActions = [];

        if ($profile['discipline_score'] < 45) {
            $healthActions[] = 'ایجاد بودجه ماهانه ساده و ثبت روزانه مخارج برای 30 روز آینده.';
            $healthActions[] = 'تنظیم انتقال خودکار پس انداز ماهانه حتی با مبلغ کم.';
        } else {
            $healthActions[] = 'بهینه سازی نسبت پس انداز به سرمایه گذاری برای رشد پایدار.';
        }

        if ($profile['stress_level'] === 'high') {
            $healthActions[] = 'تمرکز روی صندوق اضطراری 3 تا 6 ماه هزینه زندگی قبل از ریسک بیشتر.';
        } elseif ($profile['stress_level'] === 'medium') {
            $healthActions[] = 'بازبینی ماهانه برنامه مالی برای کاهش ابهام و استرس تصمیم گیری.';
        } else {
            $healthActions[] = 'استفاده از برنامه بازبینی فصلی برای حفظ انضباط مالی بلندمدت.';
        }

        if ($profile['time_horizon_level'] === 'long') {
            $healthActions[] = 'اولویت با استراتژی های بلندمدت و پرهیز از واکنش هیجانی کوتاه مدت.';
        } else {
            $healthActions[] = 'حفظ نقدشوندگی بالاتر به دلیل افق زمانی کوتاه تر.';
        }

        if (empty($answers['monthly_income_range'])) {
            $healthActions[] = 'به دلیل عدم ثبت درآمد، پیشنهادها محافظه کارانه تر ارائه شده اند.';
        }

        $explanation = 'این پیشنهادها بر اساس سطح ریسک پذیری، افق سرمایه گذاری، عادت پس انداز و میزان نگرانی مالی شما ساخته شده اند.';
        $explanation .= in_array($profile['risk_level'], ['growth', 'aggressive'], true)
            ? ' با توجه به ریسک بالاتر، مدیریت حد ضرر و بازبینی دوره ای ضروری است.'
            : ' تمرکز اصلی روی تعادل ریسک و حفظ پایداری مالی قرار گرفته است.';

        return [
            'portfolio_suggestions' => $portfolioByRisk[$profile['risk_level']],
            'financial_health_actions' => $healthActions,
            'explanation' => $explanation,
        ];
    }
}
