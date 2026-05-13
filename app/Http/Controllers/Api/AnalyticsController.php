<?php

namespace App\Http\Controllers\Api;

use App\Models\AnomalyAnalytics;
use App\Models\HealthAnalytics;
use App\Models\PortfolioTarget;
use App\Models\Transaction;
use App\Services\GapGptService;
use App\Services\PersianCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends BaseApiController
{
    public function __construct(
        private readonly PersianCalendarService $persianCalendar,
        private readonly GapGptService $gapGptService,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $today = Carbon::today();
        [$jy, $jm] = array_slice($this->persianCalendar->gregorianToJalali($today), 0, 2);
        [$monthStart] = $this->persianCalendar->monthRange($jy, $jm);

        $totalIncome = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->whereDate('date', '>=', $monthStart)->sum('amount');
        $totalExpense = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->whereDate('date', '>=', $monthStart)->sum('amount');
        $balance = $totalIncome - $totalExpense;

        $cashflow = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $jm - $i;
            $year = $jy;
            while ($month <= 0) {
                $month += 12;
                $year--;
            }
            [$start, $end] = $this->persianCalendar->monthRange($year, $month);
            if ($start->gt($today)) {
                continue;
            }
            $cashflow[] = [
                'month' => $this->persianCalendar->monthLabel($year, $month),
                'income' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->whereBetween('date', [$start, $end])->sum('amount'),
                'expense' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->whereBetween('date', [$start, $end])->sum('amount'),
            ];
        }

        $portfolioTargets = PortfolioTarget::query()->where('user_id', $user->id)->get();
        $portfolio = $portfolioTargets->map(fn (PortfolioTarget $target) => [
            'name' => $target->title,
            'value' => $balance > 0 ? (float) ($balance * $target->percentage / 100) : 0,
            'percentage' => (float) $target->percentage,
        ])->all();

        $recentTransactions = Transaction::query()
            ->with(['source', 'category'])
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_income' => (int) ($totalIncome / 10),
                'total_expense' => (int) ($totalExpense / 10),
                'balance' => (int) ($balance / 10),
            ],
            'cashflow' => $cashflow,
            'portfolio' => $portfolio,
            'anomalies' => $this->detectAnomalies($user->id, $today),
            'recent_transactions' => $recentTransactions->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->date?->toDateString(),
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'source' => $transaction->source_name,
                'category' => $transaction->category_name,
                'description' => $transaction->description,
            ])->all(),
        ]);
    }

    public function cashflow(Request $request): JsonResponse
    {
        $query = Transaction::query()->where('user_id', $this->currentUser($request)->id);
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->query('end_date'));
        }

        $groups = [];
        foreach ($query->get() as $transaction) {
            $type = is_string($transaction->type) ? strtolower(trim($transaction->type)) : '';
            if (! in_array($type, ['income', 'expense'], true)) {
                continue;
            }

            [$jy, $jm] = array_slice($this->persianCalendar->gregorianToJalali(Carbon::parse($transaction->date)), 0, 2);
            $key = "{$jy}-{$jm}";
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'month' => $this->persianCalendar->monthLabel($jy, $jm),
                    'income' => 0,
                    'expense' => 0,
                    'sort_key' => ($jy * 100) + $jm,
                ];
            }
            $groups[$key][$type] += (float) $transaction->amount;
        }

        usort($groups, fn (array $left, array $right) => $left['sort_key'] <=> $right['sort_key']);
        $result = array_map(function (array $item): array {
            unset($item['sort_key']);
            return $item;
        }, $groups);

        return response()->json(array_values($result));
    }

    public function anomalies(Request $request): JsonResponse
    {
        return response()->json(['anomalies' => $this->detectAnomalies($this->currentUser($request)->id, Carbon::today())]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $today = Carbon::today();
        [$jy] = $this->persianCalendar->gregorianToJalali($today);
        $yearStart = $this->persianCalendar->jalaliToGregorian($jy, 1, 1);

        $incomeAll = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->sum('amount');
        $expenseAll = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->sum('amount');
        $yearIncome = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->whereDate('date', '>=', $yearStart)->sum('amount');
        $yearExpense = (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->whereDate('date', '>=', $yearStart)->sum('amount');

        $incomeByCategory = Transaction::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->where('type', 'income')
            ->get()
            ->groupBy('category_name')
            ->map(fn ($items, $category) => ['category' => $category, 'amount' => (float) $items->sum('amount')])
            ->values()
            ->all();

        $expenseByCategory = Transaction::query()
            ->with('category')
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->get()
            ->groupBy('category_name')
            ->map(fn ($items, $category) => ['category' => $category, 'amount' => (float) $items->sum('amount')])
            ->values()
            ->all();

        $monthly = [];
        for ($month = 1; $month <= 12; $month++) {
            [$start, $end] = $this->persianCalendar->monthRange($jy, $month);
            $monthly[] = [
                'month' => $this->persianCalendar->monthNames()[$month - 1],
                'income' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->whereBetween('date', [$start, $end])->sum('amount'),
                'expense' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->whereBetween('date', [$start, $end])->sum('amount'),
            ];
        }

        return response()->json([
            'total_income' => $incomeAll,
            'total_expense' => $expenseAll,
            'net_savings' => $incomeAll - $expenseAll,
            'savings_rate' => $incomeAll > 0 ? (($incomeAll - $expenseAll) / $incomeAll) * 100 : 0,
            'year_income' => $yearIncome,
            'year_expense' => $yearExpense,
            'year_savings' => $yearIncome - $yearExpense,
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
            'monthly_averages' => $monthly,
        ]);
    }

    public function runAnomalyAnalysis(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $today = Carbon::today();
        $anomalies = $this->detectAnomalies($user->id, $today);

        AnomalyAnalytics::query()->updateOrCreate(
            ['user_id' => $user->id, 'analysis_date' => $today->toDateString()],
            ['anomalies_detected' => json_encode($anomalies, JSON_UNESCAPED_UNICODE), 'has_anomalies' => ! empty($anomalies)]
        );

        return response()->json(['success' => true, 'message' => 'Anomaly analysis completed successfully', 'data' => ['anomalies' => $anomalies]]);
    }

    public function latestAnomalyAnalysis(Request $request): JsonResponse
    {
        $latest = AnomalyAnalytics::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('analysis_date')
            ->first();

        if (! $latest) {
            return response()->json(['exists' => false, 'message' => 'No anomaly analysis available yet. Analysis runs every Friday.']);
        }

        return response()->json([
            'exists' => true,
            'analysis_date' => $latest->analysis_date?->toDateString(),
            'anomalies' => json_decode($latest->anomalies_detected ?? '[]', true) ?: [],
            'has_anomalies' => (bool) $latest->has_anomalies,
        ]);
    }

    public function anomalyAnalysisHistory(Request $request): JsonResponse
    {
        $history = AnomalyAnalytics::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('analysis_date')
            ->limit((int) $request->query('limit', 10))
            ->get();

        return response()->json([
            'history' => $history->map(fn (AnomalyAnalytics $item) => [
                'analysis_date' => $item->analysis_date?->toDateString(),
                'has_anomalies' => (bool) $item->has_anomalies,
                'anomalies_count' => count(json_decode($item->anomalies_detected ?? '[]', true) ?: []),
            ])->all(),
        ]);
    }

    public function runHealthAnalysis(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $today = Carbon::today();
        $start = $today->copy()->subDays(30);

        $data = [
            'total_income' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'income')->whereBetween('date', [$start, $today])->sum('amount'),
            'total_expense' => (float) Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->whereBetween('date', [$start, $today])->sum('amount'),
            'portfolio_distribution' => PortfolioTarget::query()->where('user_id', $user->id)->get(['title', 'percentage'])->toArray(),
            'period' => '30 روز گذشته',
            'analysis_date' => $today->toDateString(),
        ];

        $analysis = $this->gapGptService->analyzeFinancialHealth($data, $user->id);
        $summary = mb_strlen((string) ($analysis['analysis'] ?? '')) > 200 ? mb_substr((string) $analysis['analysis'], 0, 200).'...' : ($analysis['analysis'] ?? '');

        HealthAnalytics::query()->updateOrCreate(
            ['user_id' => $user->id, 'analysis_date' => $today->toDateString()],
            [
                'financial_health_score' => $analysis['financial_health_score'] ?? 0,
                'analysis_text' => $analysis['analysis'] ?? '',
                'recommendations' => json_encode($analysis['recommendations'] ?? [], JSON_UNESCAPED_UNICODE),
                'summary' => $summary,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Health analysis completed successfully', 'data' => $analysis]);
    }

    public function latestHealthAnalysis(Request $request): JsonResponse
    {
        $latest = HealthAnalytics::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('analysis_date')
            ->first();

        if (! $latest) {
            return response()->json(['exists' => false, 'message' => 'No health analysis available yet. Analysis runs every Friday.']);
        }

        return response()->json([
            'exists' => true,
            'analysis_date' => $latest->analysis_date?->toDateString(),
            'financial_health_score' => (float) $latest->financial_health_score,
            'analysis_text' => $latest->analysis_text,
            'recommendations' => json_decode($latest->recommendations ?? '[]', true) ?: [],
            'summary' => $latest->summary,
        ]);
    }

    public function healthAnalysisHistory(Request $request): JsonResponse
    {
        $history = HealthAnalytics::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('analysis_date')
            ->limit((int) $request->query('limit', 10))
            ->get();

        return response()->json([
            'history' => $history->map(fn (HealthAnalytics $item) => [
                'analysis_date' => $item->analysis_date?->toDateString(),
                'financial_health_score' => (float) $item->financial_health_score,
                'summary' => $item->summary,
            ])->all(),
        ]);
    }

    public function latestAnalysis(Request $request): JsonResponse
    {
        return $this->latestHealthAnalysis($request);
    }

    public function persianMonths(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: $this->persianCalendar->gregorianToJalali(Carbon::today())[0]);
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            [$start, $end] = $this->persianCalendar->monthRange($year, $month);
            $income = (float) Transaction::query()->where('user_id', $this->currentUser($request)->id)->where('type', 'income')->whereBetween('date', [$start, $end])->sum('amount');
            $expense = (float) Transaction::query()->where('user_id', $this->currentUser($request)->id)->where('type', 'expense')->whereBetween('date', [$start, $end])->sum('amount');

            $months[] = [
                'month_number' => $month,
                'month_name' => $this->persianCalendar->monthNames()[$month - 1],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'income' => $income,
                'expense' => $expense,
                'balance' => $income - $expense,
            ];
        }

        return response()->json(['year' => $year, 'months' => $months]);
    }

    private function detectAnomalies(int $userId, Carbon $today): array
    {
        [$jy, $jm] = array_slice($this->persianCalendar->gregorianToJalali($today), 0, 2);
        [$currentMonthStart] = $this->persianCalendar->monthRange($jy, $jm);
        $currentExpense = (float) Transaction::query()->where('user_id', $userId)->where('type', 'expense')->whereDate('date', '>=', $currentMonthStart)->sum('amount');
        if ($currentExpense === 0.0) {
            return [];
        }

        $threeMonthsAgo = $today->copy()->subDays(90);
        $historical = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$threeMonthsAgo, $currentMonthStart->copy()->subDay()])
            ->get();

        $monthGroups = [];
        foreach ($historical as $transaction) {
            [$hy, $hm] = array_slice($this->persianCalendar->gregorianToJalali(Carbon::parse($transaction->date)), 0, 2);
            $monthGroups["{$hy}-{$hm}"][] = (float) $transaction->amount;
        }

        $uniqueMonths = count($monthGroups);
        if ($uniqueMonths < 2) {
            return [[
                'type' => 'insufficient_data',
                'severity' => 'info',
                'description' => 'هزینه‌های این ماه '.$this->formatNumber($currentExpense).' ریال است',
                'suggestion' => 'برای دریافت پیشنهاد عالی در این زمینه نیازمند حداقل 3 ماه دیتا هستیم',
                'requires_more_data' => true,
            ]];
        }

        $average = array_sum(array_map(fn (array $amounts) => array_sum($amounts), $monthGroups)) / max($uniqueMonths, 1);
        if ($currentExpense > $average * 1.5) {
            $increase = (($currentExpense - $average) / $average) * 100;
            return [[
                'type' => 'high_spending',
                'severity' => 'warning',
                'description' => 'هزینه‌های این ماه ('.$this->formatNumber($currentExpense).' ریال) حدود '.round($increase).'% بیشتر از میانگین ماهانه ('.$this->formatNumber($average).' ریال) است',
                'suggestion' => 'برای بررسی دقیق‌تر، روی این هشدار کلیک کنید تا توضیح هوشمند دریافت کنید.',
                'requires_more_data' => false,
            ]];
        }

        return [];
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 0, '.', ',');
    }
}
