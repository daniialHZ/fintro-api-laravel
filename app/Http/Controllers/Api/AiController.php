<?php

namespace App\Http\Controllers\Api;

use App\Models\AnomalyAnalytics;
use App\Models\PortfolioTarget;
use App\Models\Transaction;
use App\Services\GapGptService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends BaseApiController
{
    public function __construct(private readonly GapGptService $gapGptService)
    {
    }

    public function analyzeFinance(Request $request): JsonResponse
    {
        $payload = $request->validate(['time_range' => ['nullable', 'string']]);
        $user = $this->currentUser($request);
        $transactions = Transaction::query()->where('user_id', $user->id)->get();
        $portfolio = PortfolioTarget::query()->where('user_id', $user->id)->get();

        $analysis = $this->gapGptService->analyzeFinancialHealth([
            'total_income' => (float) $transactions->where('type', 'income')->sum('amount'),
            'total_expense' => (float) $transactions->where('type', 'expense')->sum('amount'),
            'portfolio_distribution' => $portfolio->map(fn (PortfolioTarget $target) => ['title' => $target->title, 'percentage' => (float) $target->percentage])->all(),
            'time_range' => $payload['time_range'] ?? 'monthly',
        ], $user->id);

        return response()->json($analysis);
    }

    public function portfolioRecommendation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_portfolio' => ['required', 'array'],
        ]);

        return response()->json([
            'recommendations' => $this->gapGptService->getPortfolioRecommendations($payload['current_portfolio'], $this->currentUser($request)->id),
        ]);
    }

    public function explainAnomaly(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'anomaly_description' => ['required', 'string'],
            'context' => ['required', 'array'],
        ]);

        $user = $this->currentUser($request);
        $explanation = $this->gapGptService->explainAnomaly($payload['anomaly_description'], $payload['context'], $user->id);
        $today = Carbon::today()->toDateString();
        $record = AnomalyAnalytics::query()->firstOrCreate(
            ['user_id' => $user->id, 'analysis_date' => $today],
            ['anomalies_detected' => '[]', 'has_anomalies' => true]
        );

        $items = json_decode($record->anomalies_detected ?? '[]', true) ?: [];
        $exists = collect($items)->contains(fn (array $item) => ($item['description'] ?? null) === $payload['anomaly_description']);
        if (! $exists) {
            $items[] = [
                'type' => 'explained',
                'description' => $payload['anomaly_description'],
                'explanation' => $explanation,
                'timestamp' => Carbon::now()->toISOString(),
                'context' => $payload['context'],
            ];
            $record->anomalies_detected = json_encode($items, JSON_UNESCAPED_UNICODE);
            $record->has_anomalies = true;
            $record->save();
        }

        return response()->json([
            'success' => true,
            'explanation' => $explanation,
            'anomaly' => $payload['anomaly_description'],
            'stored_in_db' => true,
        ]);
    }

    public function explainAnomaliesBatch(Request $request): JsonResponse
    {
        $payload = $request->validate(['anomalies' => ['required', 'array']]);
        $userId = $this->currentUser($request)->id;

        $items = [];
        foreach ($payload['anomalies'] as $anomaly) {
            $items[] = [
                'anomaly' => $anomaly['description'] ?? '',
                'explanation' => $this->gapGptService->explainAnomaly($anomaly['description'] ?? '', $anomaly['context'] ?? [], $userId),
            ];
        }

        return response()->json(['success' => true, 'explanations' => $items]);
    }
}
