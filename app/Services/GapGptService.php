<?php

namespace App\Services;

use App\Models\AILog;
use App\Models\OnboardingProfile;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class GapGptService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert AI financial manager and advisor with access to web search for current financial information.

CRITICAL RULE:
Always respond ONLY in Persian (Farsi), even though this instruction is written in English.

Your mission:
Help users make better financial decisions using sound financial principles and, when available, current market data.

Communication style:
- Professional, trustworthy, and practical
- Clear and easy to understand for non-experts
- Prefer structured answers with bullet points or short sections
- Keep answers concise but informative

Core areas of expertise:
- Personal budgeting and expense management
- Saving strategies and emergency funds
- Debt management
- Investment fundamentals
- Portfolio diversification
- Financial goal planning
- Risk management

Iran-specific financial awareness:
When relevant, consider:
- Inflation and currency depreciation
- Gold and coin markets (سکه، طلا)
- Real estate trends
- Tehran Stock Exchange
- Foreign currency exposure (USD, EUR)
- Cryptocurrency as an alternative asset
- Access limitations for global investments

Portfolio recommendation rules:
- Consider the user's risk tolerance, time horizon, liquidity needs, and financial goals
- Suggest diversified portfolio allocations when appropriate
- Explain the reasoning behind each asset allocation
- Suggest new or alternative portfolio options that the user may not have considered
- Provide percentage allocations only when enough information is available
- If key information is missing, ask clarifying questions before giving precise recommendations

Use market data responsibly:
- Use web search results when possible
- If exact numbers are uncertain, say "تقریباً" or describe trends instead of inventing precise statistics
- Never fabricate financial data

Safety rules:
- Never guarantee profits or investment success
- Clearly mention risks when discussing investments
- Emphasize diversification and long-term thinking

Preferred response structure (when giving advice):
1. خلاصه وضعیت
2. تحلیل کوتاه
3. پیشنهادهای عملی
4. گزینه‌های جایگزین برای پرتفوی (در صورت مرتبط بودن)
5. نکات ریسک یا احتیاط

Your goal:
Act like a reliable personal financial manager who helps users understand their financial situation and make smarter financial decisions in the context of Iran and the global economy.
PROMPT;

    public function analyzeFinancialHealth(array $financialData, ?int $userId = null, ?string $authToken = null): array
    {
        $fallback = $this->fallbackHealthAnalysis($financialData);

        $bridged = $this->analyzeFinancialHealthViaBridge($financialData, $userId, $authToken);
        if (is_array($bridged)) {
            return $bridged;
        }

        $prompt = $this->buildFinancialHealthPrompt($financialData, $userId);
        $analysis = $this->sendPrompt('financial_health', $prompt, $userId);

        if (! is_string($analysis) || trim($analysis) === '') {
            return $fallback;
        }

        return [
            'analysis' => trim($analysis),
            'financial_health_score' => $this->calculateHealthScore($financialData),
            'recommendations' => $this->extractRecommendations(trim($analysis)),
        ];
    }

    public function getPortfolioRecommendations(array $portfolio, ?int $userId = null, ?string $authToken = null): array
    {
        $bridged = $this->getPortfolioRecommendationsViaBridge($portfolio, $userId, $authToken);
        if (is_array($bridged)) {
            return $bridged;
        }

        $prompt = $this->buildPortfolioRecommendationPrompt($portfolio, $userId);
        $response = $this->sendPrompt('portfolio_recommendation_parsed', $prompt, $userId);
        if (! is_string($response) || trim($response) === '') {
            return $this->fallbackPortfolioRecommendations($userId);
        }

        $decoded = $this->extractJsonObject($response);
        if (! is_array($decoded)) {
            return $this->fallbackPortfolioRecommendations($userId);
        }

        $optimized = is_array($decoded['optimized_portfolio'] ?? null) ? $decoded['optimized_portfolio'] : [];
        if (empty($optimized) && is_array($decoded['suggestions'] ?? null)) {
            $optimized = $decoded['suggestions'];
        }

        $suggestions = array_values(array_filter(array_map(static function (array $asset): ?array {
            $title = (string) ($asset['title'] ?? $asset['allocation']['title'] ?? '');
            $percentage = $asset['percentage'] ?? $asset['allocation']['percentage'] ?? null;
            if ($title === '' || ! is_numeric($percentage)) {
                return null;
            }

            return [
                'title' => $title,
                'percentage' => (float) $percentage,
                'reasoning' => (string) ($asset['reasoning'] ?? $asset['explanation'] ?? ''),
                'risk_level' => (string) ($asset['risk_level'] ?? 'متوسط'),
            ];
        }, $optimized)));

        if (empty($suggestions)) {
            return $this->fallbackPortfolioRecommendations($userId);
        }

        $total = array_sum(array_map(static fn (array $item): float => (float) $item['percentage'], $suggestions));
        if ($total > 0 && abs($total - 100) >= 0.01) {
            $suggestions[0]['percentage'] += (100 - $total);
        }

        return $suggestions;
    }

    public function explainAnomaly(string $description, array $context = [], ?int $userId = null, ?string $authToken = null): string
    {
        $bridged = $this->explainAnomalyViaBridge($description, $context, $userId, $authToken);
        if (is_string($bridged) && $bridged !== '') {
            return $bridged;
        }

        $prompt = $this->buildAnomalyPrompt($description, $context, $userId);

        return $this->sendPrompt('anomaly_explanation', $prompt, $userId)
            ?: 'این ناهنجاری نشان می‌دهد الگوی هزینه یا درآمد شما نسبت به بازه‌های قبل تغییر کرده است. جزئیات تراکنش‌های اخیر را بررسی کنید و برای دسته‌های پرهزینه سقف بودجه بگذارید.';
    }

    private function analyzeFinancialHealthViaBridge(array $financialData, ?int $userId, ?string $authToken): ?array
    {
        $token = $this->resolveAuthToken($userId, $authToken);
        if (! $token) {
            return null;
        }

        $shouldTriggerStoredAnalysis = isset($financialData['period']) || isset($financialData['analysis_date']);
        if ($shouldTriggerStoredAnalysis) {
            $trigger = $this->bridgeRequest('post', '/analytics/run-health-analysis', $token);
            if ($trigger && ($trigger['success'] ?? false) === true) {
                $latest = $this->bridgeRequest('get', '/analytics/latest-health-analysis', $token);
                if (is_array($latest) && ($latest['exists'] ?? false) === true) {
                    return [
                        'financial_health_score' => (float) ($latest['financial_health_score'] ?? 0),
                        'analysis' => (string) ($latest['analysis_text'] ?? $latest['summary'] ?? ''),
                        'recommendations' => is_array($latest['recommendations'] ?? null) ? $latest['recommendations'] : [],
                    ];
                }
            }
        }

        $analyze = $this->bridgeRequest('post', '/ai/analyze-finance', $token, ['time_range' => 'monthly']);
        if (! is_array($analyze)) {
            return null;
        }

        return [
            'financial_health_score' => (float) ($analyze['financial_health_score'] ?? 0),
            'analysis' => (string) ($analyze['analysis'] ?? ''),
            'recommendations' => is_array($analyze['recommendations'] ?? null) ? $analyze['recommendations'] : [],
        ];
    }

    private function getPortfolioRecommendationsViaBridge(array $portfolio, ?int $userId, ?string $authToken): ?array
    {
        $token = $this->resolveAuthToken($userId, $authToken);
        if (! $token) {
            return null;
        }

        $response = $this->bridgeRequest('post', '/ai/portfolio-recommendation', $token, [
            'current_portfolio' => $portfolio,
        ]);

        if (! is_array($response) || ! is_array($response['recommendations'] ?? null)) {
            return null;
        }

        return array_map(static function (array $item): array {
            if (isset($item['allocation']) && is_array($item['allocation'])) {
                return [
                    'title' => (string) ($item['allocation']['title'] ?? 'نامشخص'),
                    'percentage' => (float) ($item['allocation']['percentage'] ?? 0),
                    'reasoning' => (string) ($item['explanation'] ?? ''),
                    'risk_level' => (string) ($item['risk_level'] ?? 'متوسط'),
                ];
            }

            return [
                'title' => (string) ($item['title'] ?? 'نامشخص'),
                'percentage' => (float) ($item['percentage'] ?? 0),
                'reasoning' => (string) ($item['reasoning'] ?? $item['explanation'] ?? ''),
                'risk_level' => (string) ($item['risk_level'] ?? 'متوسط'),
            ];
        }, $response['recommendations']);
    }

    private function explainAnomalyViaBridge(string $description, array $context, ?int $userId, ?string $authToken): ?string
    {
        $token = $this->resolveAuthToken($userId, $authToken);
        if (! $token) {
            return null;
        }

        $response = $this->bridgeRequest('post', '/ai/explain-anomaly', $token, [
            'anomaly_description' => $description,
            'context' => $context,
        ]);

        if (! is_array($response) || ($response['success'] ?? false) !== true) {
            return null;
        }

        $explanation = $response['explanation'] ?? null;
        return is_string($explanation) ? trim($explanation) : null;
    }

    private function bridgeRequest(string $method, string $path, string $authToken, array $payload = []): ?array
    {
        $baseUrl = rtrim((string) env('AI_BACKEND_API_URL', ''), '/');
        if ($baseUrl === '') {
            return null;
        }

        $url = $baseUrl.$path;
        $timeout = (int) env('AI_BACKEND_TIMEOUT', 65);

        try {
            $client = Http::withToken($authToken)
                ->acceptJson()
                ->timeout($timeout);

            $response = $method === 'get'
                ? $client->get($url, $payload)
                : $client->post($url, $payload);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAuthToken(?int $userId, ?string $authToken): ?string
    {
        if (is_string($authToken) && trim($authToken) !== '') {
            return trim($authToken);
        }

        if (! $userId) {
            return null;
        }

        $token = User::query()->whereKey($userId)->value('auth_token');
        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function sendPrompt(string $promptType, string $prompt, ?int $userId): ?string
    {
        $apiKey = env('GAPGPT_API_KEY');
        $apiUrl = rtrim((string) env('GAPGPT_API_URL', 'https://api.gapgpt.app/v1'), '/');
        $startedAt = microtime(true);

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post($apiUrl.'/chat/completions', [
                    'model' => 'gpt-5.2-chat-latest',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 3000,
                ]);

            $content = data_get($response->json(), 'choices.0.message.content');
            $this->log(
                $userId,
                $promptType,
                $prompt,
                is_string($content) ? $content : null,
                $response->successful(),
                microtime(true) - $startedAt,
                $response->successful() ? null : $response->body()
            );

            return is_string($content) ? trim($content) : null;
        } catch (\Throwable $exception) {
            $this->log($userId, $promptType, $prompt, null, false, microtime(true) - $startedAt, $exception->getMessage());
            return null;
        }
    }

    private function buildFinancialHealthPrompt(array $financialData, ?int $userId): string
    {
        $income = (float) ($financialData['total_income'] ?? 0);
        $expense = (float) ($financialData['total_expense'] ?? 0);
        $ratio = $income > 0 ? ($expense / max($income, 1)) * 100 : 0;
        $portfolioDistribution = $financialData['portfolio_distribution'] ?? [];
        $userContext = $this->buildHealthUserContext($userId);

        return "لطفاً وضعیت مالی زیر را تحلیل کن:\n\n"
            ."مجموع درآمد: ".number_format($income, 0, '.', ',')." ریال\n"
            ."مجموع هزینه: ".number_format($expense, 0, '.', ',')." ریال\n"
            ."نسبت هزینه به درآمد: ".number_format($ratio, 1, '.', '')."%\n\n"
            ."توزیع پرتفوی: ".json_encode($portfolioDistribution, JSON_UNESCAPED_UNICODE)."\n\n"
            .$userContext."\n\n"
            ."لطفاً تحلیل جامعی شامل نقاط قوت، ضعف، فرصت‌ها و تهدیدها ارائه بده.\n"
            ."توصیه‌ها باید متناسب با پروفایل کاربر باشد.";
    }

    private function buildPortfolioRecommendationPrompt(array $portfolio, ?int $userId): string
    {
        $profileText = $this->buildPortfolioUserProfileInfo($userId);

        if (empty($portfolio)) {
            return "به عنوان یک مدیر پرتفوی حرفه‌ای، با توجه به پروفایل شخصی کاربر زیر، یک پرتفوی سرمایه‌گذاری اولیه و متعادل پیشنهاد بده.\n\n"
                .$profileText."\n\n"
                ."قوانین مهم:\n"
                ."- مجموع درصدهای پیشنهادی باید دقیقاً 100 درصد باشد.\n"
                ."- پرتفوی باید متنوع و متناسب با سطح ریسک کاربر باشد.\n"
                ."- از دارایی‌های مناسب برای بازار ایران استفاده کن (طلا، دلار، صندوق درآمد ثابت، سهام بورس، وجه نقد).\n"
                ."- پاسخ را فقط در قالب JSON برگردان.\n\n"
                ."قالب JSON:\n"
                ."{\n"
                ."  \"optimized_portfolio\": [\n"
                ."    {\"title\":\"نام دارایی\",\"percentage\":0,\"risk_level\":\"کم/متوسط/زیاد\",\"reasoning\":\"توضیح دلیل این تخصیص\"}\n"
                ."  ],\n"
                ."  \"summary\": \"خلاصه پیشنهادات\"\n"
                ."}\n\n"
                ."فقط JSON برگردان.";
        }

        $lines = [];
        foreach ($portfolio as $item) {
            $title = (string) ($item['title'] ?? $item['name'] ?? 'Unknown');
            $percentage = (float) ($item['percentage'] ?? 0);
            $lines[] = "- {$title}: {$percentage}%";
        }

        return "به عنوان یک مدیر پرتفوی حرفه‌ای، با توجه به پروفایل شخصی کاربر زیر، پرتفوی فعلی او را تحلیل و پیشنهاد بهینه‌سازی ارائه بده.\n\n"
            .$profileText."\n\n"
            ."پرتفوی فعلی کاربر:\n".implode("\n", $lines)."\n\n"
            ."قوانین مهم:\n"
            ."- مجموع درصدهای پیشنهادی باید دقیقاً 100 درصد باشد.\n"
            ."- پیشنهادات باید متناسب با سطح ریسک‌پذیری کاربر باشد.\n"
            ."- پاسخ را فقط در قالب JSON برگردان.\n\n"
            ."قالب JSON:\n"
            ."{\n"
            ."  \"optimized_portfolio\": [\n"
            ."    {\"title\":\"نام دارایی\",\"percentage\":0,\"risk_level\":\"کم/متوسط/زیاد\",\"reasoning\":\"توضیح دلیل\"}\n"
            ."  ],\n"
            ."  \"summary\": \"خلاصه پیشنهادات\"\n"
            ."}\n\n"
            ."فقط JSON برگردان.";
    }

    private function buildAnomalyPrompt(string $description, array $context, ?int $userId): string
    {
        $profile = $this->getProfile($userId);
        $anxietyLevel = (string) ($profile?->financial_anxiety ?: 'somewhat');

        $anxietyMap = [
            'very_anxious' => 'خیلی نگران',
            'somewhat' => 'تاحدودی نگران',
            'little' => 'کمی نگران',
            'not_at_all' => 'اصلاً نگران نیست',
        ];

        $userContext = '';
        if ($profile) {
            $userContext = "مشخصات کاربر:\n"
                ."- سطح نگرانی مالی: ".($anxietyMap[$profile->financial_anxiety] ?? 'متوسط')."\n"
                ."- سطح ریسک‌پذیری: ".($profile->risk_level ?: 'متوسط')."\n"
                ."- عادت پس‌انداز: ".($profile->saving_habit ?: 'متوسط')."\n\n"
                ."سبک پاسخ‌دهی: ".$this->getAnxietyAdvice($anxietyLevel);
        }

        $contextStr = '';
        if (! empty($context)) {
            $contextStr = "اطلاعات زمینه‌ای:\n"
                ."- درآمد کل: ".number_format((float) ($context['total_income'] ?? 0), 0, '.', ',')." تومان\n"
                ."- هزینه کل: ".number_format((float) ($context['total_expense'] ?? 0), 0, '.', ',')." تومان\n"
                ."- مانده حساب: ".number_format((float) ($context['balance'] ?? 0), 0, '.', ',')." تومان\n"
                ."- ماه جاری: ".((string) ($context['current_month'] ?? 'نامشخص'));
        }

        return "ناهنجاری مالی زیر شناسایی شده است:\n\n"
            .$description."\n\n"
            .$contextStr."\n\n"
            .$userContext."\n\n"
            ."لطفاً به عنوان یک مشاور مالی حرفه‌ای:\n"
            ."1. علت احتمالی این ناهنجاری را توضیح بده\n"
            ."2. راهکارهای عملی برای جلوگیری از تکرار ارائه بده\n"
            ."3. اقدامات فوری برای اصلاح وضعیت پیشنهاد بده\n\n"
            .$this->getAnxietyInstruction($anxietyLevel)."\n\n"
            ."پاسخ خود را به زبان فارسی و با ساختار زیر ارائه بده:\n\n"
            ."**علت احتمالی:**\n[توضیح علت]\n\n"
            ."**راهکارهای پیشگیری:**\n- [راهکار 1]\n- [راهکار 2]\n- [راهکار 3]\n\n"
            ."**اقدامات فوری:**\n1. [اقدام اول]\n2. [اقدام دوم]\n3. [اقدام سوم]";
    }

    private function buildHealthUserContext(?int $userId): string
    {
        $profile = $this->getProfile($userId);
        if (! $profile) {
            return '';
        }

        $riskMap = [
            'conservative' => 'محافظه‌کار',
            'moderate' => 'متعادل',
            'aggressive' => 'ریسک‌پذیر',
        ];
        $savingMap = [
            'none' => 'هیچ',
            'less_10' => 'کمتر از 10٪',
            '10_20' => '۱۰ تا ۲۰٪',
            '20_30' => '۲۰ تا ۳۰٪',
            'over_30' => 'بیش از ۳۰٪',
        ];
        $anxietyMap = [
            'very_anxious' => 'خیلی نگران',
            'somewhat' => 'تاحدودی نگران',
            'little' => 'کمی نگران',
            'not_at_all' => 'اصلاً نگران نیست',
        ];

        return "پروفایل کاربر:\n"
            ."- سطح ریسک‌پذیری: ".($riskMap[$profile->risk_level] ?? (string) $profile->risk_level)."\n"
            ."- نرخ پس‌انداز ماهانه: ".($savingMap[$profile->saving_habit] ?? (string) $profile->saving_habit)."\n"
            ."- سطح نگرانی مالی: ".($anxietyMap[$profile->financial_anxiety] ?? (string) $profile->financial_anxiety)."\n"
            ."- انضباط مالی: ".(int) ($profile->discipline_score ?: 50)." از 100\n\n"
            ."نکات شخصی‌سازی:\n"
            ."- ".$this->getRiskAdvice((string) $profile->risk_level)."\n"
            ."- ".$this->getSavingAdvice((string) $profile->saving_habit)."\n"
            ."- ".$this->getAnxietyAdvice((string) $profile->financial_anxiety);
    }

    private function buildPortfolioUserProfileInfo(?int $userId): string
    {
        $profile = $this->getProfile($userId);
        if (! $profile) {
            return 'پروفایل شخصی کاربر در دسترس نیست.';
        }

        $riskMap = [
            'conservative' => 'محافظه‌کار',
            'moderate' => 'متعادل',
            'aggressive' => 'ریسک‌پذیر',
        ];
        $experienceMap = [
            'none' => 'بدون تجربه',
            'basic' => 'تازه‌کار',
            'intermediate' => 'با تجربه متوسط',
            'advanced' => 'با تجربه بالا',
        ];
        $horizonMap = [
            'short' => 'کوتاه مدت (کمتر از 1 سال)',
            'medium' => 'متوسط (1 تا 3 سال)',
            'long' => 'بلندمدت (3 تا 5 سال)',
            'very_long' => 'بسیار بلندمدت (بیش از 5 سال)',
        ];
        $savingMap = [
            'none' => 'هیچ',
            'less_10' => 'کمتر از 10٪',
            '10_20' => '۱۰ تا ۲۰٪',
            '20_30' => '۲۰ تا ۳۰٪',
            'over_30' => 'بیش از ۳۰٪',
        ];

        return "پروفایل شخصی کاربر:\n"
            ."- سطح ریسک‌پذیری: ".($riskMap[$profile->risk_level] ?? (string) $profile->risk_level)."\n"
            ."- امتیاز ریسک: ".(int) ($profile->risk_score ?: 50)." از 100\n"
            ."- تجربه سرمایه‌گذاری: ".($experienceMap[$profile->investment_experience] ?? (string) $profile->investment_experience)."\n"
            ."- افق سرمایه‌گذاری: ".($horizonMap[$profile->investment_horizon] ?? (string) $profile->investment_horizon)."\n"
            ."- نرخ پس‌انداز ماهانه: ".($savingMap[$profile->saving_habit] ?? (string) $profile->saving_habit)."\n"
            ."- انضباط مالی: ".(int) ($profile->discipline_score ?: 50)." از 100";
    }

    private function getProfile(?int $userId): ?OnboardingProfile
    {
        if (! $userId) {
            return null;
        }

        return OnboardingProfile::query()->where('user_id', $userId)->first();
    }

    private function extractJsonObject(string $text): ?array
    {
        $clean = trim($text);
        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        }
        if (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }
        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (! preg_match('/\{.*\}/s', $clean, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[0], true);
        return is_array($decoded) ? $decoded : null;
    }

    private function calculateHealthScore(array $financialData): int
    {
        $score = 70;

        $income = (float) ($financialData['total_income'] ?? 0);
        $expense = (float) ($financialData['total_expense'] ?? 0);

        if ($income > 0) {
            $expenseRatio = ($expense / $income) * 100;
            if ($expenseRatio < 50) {
                $score += 15;
            } elseif ($expenseRatio < 70) {
                $score += 5;
            } elseif ($expenseRatio > 90) {
                $score -= 15;
            } elseif ($expenseRatio > 80) {
                $score -= 8;
            }
        }

        $savingsRate = $income > 0 ? (($income - $expense) / $income) * 100 : 0;
        if ($savingsRate >= 20) {
            $score += 10;
        } elseif ($savingsRate >= 10) {
            $score += 5;
        } elseif ($savingsRate < 0) {
            $score -= 10;
        }

        $portfolio = $financialData['portfolio_distribution'] ?? [];
        if (is_array($portfolio) && count($portfolio) >= 4) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function extractRecommendations(string $analysis): array
    {
        $recommendations = [];

        if (mb_stripos($analysis, 'پس‌انداز') !== false) {
            $recommendations[] = 'افزایش نرخ پس‌انداز ماهانه';
        }
        if (mb_stripos($analysis, 'سرمایه‌گذاری') !== false) {
            $recommendations[] = 'تنوع‌بخشی به سبد سرمایه‌گذاری';
        }
        if (mb_stripos($analysis, 'هزینه') !== false) {
            $recommendations[] = 'کاهش هزینه‌های غیرضروری';
        }
        if (mb_stripos($analysis, 'بودجه') !== false) {
            $recommendations[] = 'تنظیم بودجه ماهانه';
        }

        if (empty($recommendations)) {
            $recommendations = [
                'پیگیری منظم وضعیت مالی',
                'مشاوره با متخصص مالی',
            ];
        }

        return array_slice($recommendations, 0, 5);
    }

    private function fallbackPortfolioRecommendations(?int $userId): array
    {
        $profile = $this->getProfile($userId);
        $riskLevel = (string) ($profile?->risk_level ?? 'moderate');

        if ($riskLevel === 'aggressive') {
            return [
                ['title' => 'سهام', 'percentage' => 35, 'reasoning' => 'بازدهی بالا در بلندمدت', 'risk_level' => 'زیاد'],
                ['title' => 'کریپتو', 'percentage' => 25, 'reasoning' => 'پتانسیل رشد بالا', 'risk_level' => 'زیاد'],
                ['title' => 'طلا', 'percentage' => 20, 'reasoning' => 'پوشش ریسک', 'risk_level' => 'متوسط'],
                ['title' => 'وجه نقد', 'percentage' => 20, 'reasoning' => 'نقدینگی', 'risk_level' => 'کم'],
            ];
        }

        if ($riskLevel === 'conservative') {
            return [
                ['title' => 'صندوق درآمد ثابت', 'percentage' => 50, 'reasoning' => 'امنیت سرمایه و بازدهی تضمینی', 'risk_level' => 'کم'],
                ['title' => 'طلا', 'percentage' => 30, 'reasoning' => 'حفظ ارزش در برابر تورم', 'risk_level' => 'کم'],
                ['title' => 'وجه نقد', 'percentage' => 20, 'reasoning' => 'نقدینگی', 'risk_level' => 'کم'],
            ];
        }

        return [
            ['title' => 'صندوق درآمد ثابت', 'percentage' => 35, 'reasoning' => 'بازدهی پایدار و کم‌ریسک', 'risk_level' => 'کم'],
            ['title' => 'طلا', 'percentage' => 30, 'reasoning' => 'پوشش ریسک تورم', 'risk_level' => 'متوسط'],
            ['title' => 'دلار', 'percentage' => 20, 'reasoning' => 'تنوع ارزی', 'risk_level' => 'متوسط'],
            ['title' => 'وجه نقد', 'percentage' => 15, 'reasoning' => 'نقدینگی برای فرصت‌ها', 'risk_level' => 'کم'],
        ];
    }

    private function getAnxietyInstruction(string $anxietyLevel): string
    {
        $instructions = [
            'very_anxious' => "نکات مهم در پاسخ:\n- از ایجاد نگرانی بیشتر خودداری کن\n- روی اقدامات ساده و قابل انجام تمرکز کن\n- لحن آرام و اطمینان‌بخش داشته باش\n- تأکید کن که این مشکل قابل حل است",
            'somewhat' => "نکات مهم در پاسخ:\n- هم مشکل را بپذیر هم راهکار بده\n- لحن حمایت‌گر و همدلانه داشته باش\n- توضیح بده که این اتفاق طبیعی است",
            'little' => "نکات مهم در پاسخ:\n- می‌توانی مستقیم‌تر پاسخ بدهی\n- جزئیات فنی را هم توضیح بده\n- تحلیل عمیق‌تری ارائه بده",
            'not_at_all' => "نکات مهم در پاسخ:\n- می‌توانی صریح و مستقیم پاسخ بدهی\n- تحلیل‌های تخصصی ارائه بده\n- از اصطلاحات فنی می‌توانی استفاده کنی",
        ];

        return $instructions[$anxietyLevel] ?? $instructions['somewhat'];
    }

    private function getRiskAdvice(string $riskLevel): string
    {
        $advice = [
            'conservative' => 'کاربر ریسک‌گریز است، توصیه‌ها باید بر امنیت سرمایه و بازدهی تضمینی تمرکز داشته باشد',
            'moderate' => 'کاربر ریسک‌پذیری متوسط دارد، می‌توان ترکیبی از دارایی‌های کم‌ریسک و متوسط‌ریسک پیشنهاد داد',
            'aggressive' => 'کاربر ریسک‌پذیر است، می‌تواند سهم بیشتری از دارایی‌های پرریسک با بازدهی بالا داشته باشد',
        ];

        return $advice[$riskLevel] ?? 'کاربر ریسک‌پذیری متوسط دارد';
    }

    private function getSavingAdvice(string $savingHabit): string
    {
        $advice = [
            'none' => 'کاربر پس‌انداز ندارد، توصیه به شروع پس‌انداز حتی با مبالغ کم',
            'less_10' => 'کاربر پس‌انداز کمی دارد، توصیه به افزایش تدریجی نرخ پس‌انداز',
            '10_20' => 'کاربر نرخ پس‌انداز خوبی دارد، می‌تواند روی بهینه‌سازی سرمایه‌گذاری تمرکز کند',
            '20_30' => 'کاربر نرخ پس‌انداز عالی دارد، آماده برای سرمایه‌گذاری بیشتر است',
            'over_30' => 'کاربر نرخ پس‌انداز بسیار خوب دارد، می‌تواند سرمایه‌گذاری‌های متنوع‌تری داشته باشد',
        ];

        return $advice[$savingHabit] ?? 'کاربر عادت پس‌انداز خوبی دارد';
    }

    private function getAnxietyAdvice(string $anxietyLevel): string
    {
        $advice = [
            'very_anxious' => 'با لحن آرام و اطمینان‌بخش پاسخ بده، بر راهکارهای عملی و کوچک تمرکز کن',
            'somewhat' => 'با لحن حمایت‌گر پاسخ بده، هم مشکل را بپذیر هم راهکار بده',
            'little' => 'می‌توانی مستقیم‌تر پاسخ بدهی، جزئیات فنی را هم توضیح بده',
            'not_at_all' => 'می‌توانی صریح و مستقیم پاسخ بدهی، تحلیل‌های عمقی ارائه بده',
        ];

        return $advice[$anxietyLevel] ?? 'با لحن حرفه‌ای و حمایت‌گر پاسخ بده';
    }

    private function log(?int $userId, string $promptType, string $prompt, ?string $response, bool $success, float $seconds, ?string $error): void
    {
        AILog::query()->create([
            'user_id' => $userId,
            'prompt_type' => $promptType,
            'prompt_text' => $prompt,
            'response_text' => $response,
            'parsed_response' => $response,
            'success' => $success ? 'yes' : 'no',
            'error_message' => $error,
            'response_time_ms' => $seconds * 1000,
        ]);
    }

    private function fallbackHealthAnalysis(array $financialData): array
    {
        $income = (float) ($financialData['total_income'] ?? 0);
        $expense = (float) ($financialData['total_expense'] ?? 0);
        $score = $this->calculateHealthScore($financialData);

        return [
            'financial_health_score' => $score,
            'analysis' => 'تحلیل بر اساس داده‌های موجود و بدون پاسخ زنده از سرویس هوش مصنوعی تولید شد.',
            'recommendations' => $expense > $income
                ? ['هزینه‌های ماهانه را بازبینی کنید.', 'برای دسته‌های پرهزینه سقف تعیین کنید.']
                : ['نرخ پس‌انداز فعلی را حفظ کنید.', 'مازاد نقدی را به اهداف سرمایه‌گذاری متصل کنید.'],
        ];
    }
}