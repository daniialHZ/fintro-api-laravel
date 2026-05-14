<?php

namespace App\Http\Controllers\Api;

use App\Models\AILog;
use App\Models\PortfolioTarget;
use App\Services\GapGptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends BaseApiController
{
    public function __construct(private readonly GapGptService $gapGptService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $targets = PortfolioTarget::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->get();

        return response()->json($targets->map(fn (PortfolioTarget $target) => $this->serialize($target))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $user = $this->currentUser($request);
        $exists = PortfolioTarget::query()
            ->where('title', $payload['title'])
            ->where('user_id', $user->id)
            ->exists();
        if ($exists) {
            return response()->json(['detail' => 'Title already exists'], 400);
        }

        $total = (float) PortfolioTarget::query()->where('user_id', $user->id)->sum('percentage');
        if ($total + (float) $payload['percentage'] > 100) {
            return response()->json(['detail' => 'Total percentage cannot exceed 100%'], 400);
        }

        $target = PortfolioTarget::query()->create([
            ...$payload,
            'user_id' => $user->id,
        ]);

        return response()->json($this->serialize($target), 201);
    }

    public function update(Request $request, PortfolioTarget $portfolio): JsonResponse
    {
        if ($portfolio->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Portfolio target not found'], 404);
        }

        $payload = $request->validate([
            'title' => ['nullable', 'string'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $portfolio->fill($payload);
        $portfolio->save();

        return response()->json($this->serialize($portfolio));
    }

    public function destroy(Request $request, PortfolioTarget $portfolio): JsonResponse
    {
        if ($portfolio->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Portfolio target not found'], 404);
        }

        $portfolio->delete();

        return response()->json(null, 204);
    }

    public function validatePortfolio(Request $request): JsonResponse
    {
        $total = (float) PortfolioTarget::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->sum('percentage');

        $isValid = abs($total - 100) < 0.01;

        return response()->json([
            'total_percentage' => $total,
            'is_valid' => $isValid,
            'message' => $isValid ? 'Portfolio is balanced' : "Total percentage is {$total}%, should be 100%",
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $portfolio = PortfolioTarget::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (PortfolioTarget $target) => [
                'title' => $target->title,
                'percentage' => (float) $target->percentage,
            ])
            ->values()
            ->all();

        $suggestions = $this->gapGptService->getPortfolioRecommendations($portfolio, $user->id, $user->auth_token);

        return response()->json([
            'success' => ! empty($suggestions),
            'suggestions' => $suggestions,
            'message' => ! empty($suggestions)
                ? 'AI portfolio recommendations generated successfully'
                : 'Unable to generate portfolio recommendations',
        ]);
    }

    public function recommendationsHistory(Request $request): JsonResponse
    {
        $history = AILog::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->where('prompt_type', 'portfolio_recommendation_parsed')
            ->where('success', 'yes')
            ->orderByDesc('created_at')
            ->limit((int) $request->query('limit', 10))
            ->get();

        return response()->json([
            'history' => $history->map(fn (AILog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toISOString(),
                'prompt_text' => $log->prompt_text ? mb_substr($log->prompt_text, 0, 500) : '',
                'response_text' => $log->response_text ? mb_substr($log->response_text, 0, 1000) : '',
                'parsed_response' => $log->parsed_response ?: '',
            ])->all(),
        ]);
    }

    private function serialize(PortfolioTarget $target): array
    {
        return [
            'id' => $target->id,
            'title' => $target->title,
            'percentage' => (float) $target->percentage,
        ];
    }
}