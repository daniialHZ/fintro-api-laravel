<?php

namespace App\Services;

use App\Models\AILog;
use Illuminate\Support\Facades\Http;

class GapGptService
{
    public function analyzeFinancialHealth(array $financialData, ?int $userId = null): array
    {
        $fallback = $this->fallbackHealthAnalysis($financialData);
        $prompt = 'Analyze this financial snapshot and return JSON with financial_health_score, analysis, recommendations: '.json_encode($financialData, JSON_UNESCAPED_UNICODE);

        $response = $this->sendPrompt('financial_health', $prompt, $userId);
        if (! $response) {
            return $fallback;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public function getPortfolioRecommendations(array $portfolio, ?int $userId = null): array
    {
        if (empty($portfolio)) {
            return [];
        }

        $prompt = 'Suggest a balanced portfolio as JSON array with title, percentage, reasoning, risk_level for: '.json_encode($portfolio, JSON_UNESCAPED_UNICODE);
        $response = $this->sendPrompt('portfolio_recommendation_parsed', $prompt, $userId);
        $decoded = $response ? json_decode($response, true) : null;

        if (is_array($decoded)) {
            return $decoded;
        }

        return array_map(static fn (array $item) => [
            'title' => $item['title'],
            'percentage' => (float) $item['percentage'],
            'reasoning' => 'بر اساس وضعیت فعلی پرتفوی ثبت شده است.',
            'risk_level' => 'متوسط',
        ], $portfolio);
    }

    public function explainAnomaly(string $description, array $context = [], ?int $userId = null): string
    {
        $prompt = 'Explain this financial anomaly in Persian with practical actions: '.json_encode([
            'description' => $description,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE);

        return $this->sendPrompt('anomaly_explanation', $prompt, $userId)
            ?: 'این ناهنجاری نشان می دهد الگوی هزینه یا درآمد شما نسبت به بازه های قبل تغییر کرده است. جزئیات تراکنش های اخیر و دسته های هزینه را بررسی کنید و اگر تغییر موقتی نیست، برای آن سقف بودجه تعیین کنید.';
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
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a financial assistant. Return concise JSON when requested.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            $content = data_get($response->json(), 'choices.0.message.content');
            $this->log($userId, $promptType, $prompt, is_string($content) ? $content : null, $response->successful(), microtime(true) - $startedAt, $response->successful() ? null : $response->body());

            return is_string($content) ? trim($content) : null;
        } catch (\Throwable $exception) {
            $this->log($userId, $promptType, $prompt, null, false, microtime(true) - $startedAt, $exception->getMessage());

            return null;
        }
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
        $savingsRate = $income > 0 ? max(min((($income - $expense) / $income) * 100, 100), -100) : 0;
        $score = (int) max(min(round(($savingsRate + 100) / 2), 100), 0);

        return [
            'financial_health_score' => $score,
            'analysis' => 'تحلیل بر اساس داده های موجود و بدون پاسخ زنده از سرویس هوش مصنوعی تولید شد.',
            'recommendations' => $expense > $income
                ? ['هزینه های ماهانه را بازبینی کنید.', 'برای دسته های پرهزینه سقف تعیین کنید.']
                : ['نرخ پس انداز فعلی را حفظ کنید.', 'مازاد نقدی را به اهداف سرمایه گذاری متصل کنید.'],
        ];
    }
}
