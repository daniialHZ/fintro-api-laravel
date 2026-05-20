<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => ['status' => 'healthy']);
    Route::get('/', fn () => ['message' => 'Financial Dashboard API', 'status' => 'running']);

    Route::prefix('auth')->group(function (): void {
        Route::post('/signup', [AuthController::class, 'signup']);
        Route::post('/signin', [AuthController::class, 'signin']);
    });

    Route::prefix('invite')->group(function (): void {
        Route::get('/validate', [InviteController::class, 'validateCode']);
    });

    Route::middleware('auth.token')->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
        });

        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/all', [TransactionController::class, 'all']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
        Route::post('/transactions/import-excel', [TransactionController::class, 'importExcel']);
        Route::get('/transactions/debug/count', [TransactionController::class, 'count']);
        Route::get('/transactions/debug/all', [TransactionController::class, 'debugAll']);

        Route::apiResource('goals', GoalController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('wealth', WealthController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/wealth/summary', [WealthController::class, 'summary']);
        Route::get('/wealth/debug/check-encryption', [WealthController::class, 'debugEncryption']);

        Route::apiResource('portfolio', PortfolioController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/portfolio/validate', [PortfolioController::class, 'validatePortfolio']);
        Route::get('/portfolio/recommendations', [PortfolioController::class, 'recommendations']);
        Route::get('/portfolio/recommendations/history', [PortfolioController::class, 'recommendationsHistory']);

        Route::post('/suggestions', [SuggestionController::class, 'store']);

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllRead']);
            Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
        });

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/sources', [SourceController::class, 'index']);
        Route::post('/sources', [SourceController::class, 'store']);
        Route::put('/sources/{source}', [SourceController::class, 'update']);
        Route::delete('/sources/{source}', [SourceController::class, 'destroy']);

        Route::prefix('onboarding')->group(function (): void {
            Route::post('/submit', [OnboardingController::class, 'submit']);
            Route::get('/profile', [OnboardingController::class, 'profile']);
            Route::patch('/answers', [OnboardingController::class, 'patchAnswers']);
        });

        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/email', [ProfileController::class, 'updateEmail']);
            Route::put('/password', [ProfileController::class, 'updatePassword']);
            Route::post('/refresh-recommendations', [ProfileController::class, 'refreshRecommendations']);
            Route::get('/debug', [ProfileController::class, 'debug']);
        });

        Route::prefix('invite')->group(function (): void {
            Route::get('/codes', [InviteController::class, 'index']);
            Route::post('/codes', [InviteController::class, 'store']);
            Route::post('/codes/generate', [InviteController::class, 'generate']);
            Route::delete('/codes/{inviteCode}', [InviteController::class, 'destroy']);
        });

        Route::prefix('analytics')->group(function (): void {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/cashflow', [AnalyticsController::class, 'cashflow']);
            Route::get('/anomalies', [AnalyticsController::class, 'anomalies']);
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::post('/run-anomaly-analysis', [AnalyticsController::class, 'runAnomalyAnalysis']);
            Route::get('/latest-anomaly-analysis', [AnalyticsController::class, 'latestAnomalyAnalysis']);
            Route::get('/anomaly-analysis-history', [AnalyticsController::class, 'anomalyAnalysisHistory']);
            Route::post('/run-health-analysis', [AnalyticsController::class, 'runHealthAnalysis']);
            Route::get('/latest-health-analysis', [AnalyticsController::class, 'latestHealthAnalysis']);
            Route::get('/health-analysis-history', [AnalyticsController::class, 'healthAnalysisHistory']);
            Route::get('/latest-analysis', [AnalyticsController::class, 'latestAnalysis']);
            Route::get('/persian-months', [AnalyticsController::class, 'persianMonths']);
        });

        Route::prefix('ai')->group(function (): void {
            Route::post('/analyze-finance', [AiController::class, 'analyzeFinance']);
            Route::post('/portfolio-recommendation', [AiController::class, 'portfolioRecommendation']);
            Route::post('/explain-anomaly', [AiController::class, 'explainAnomaly']);
            Route::post('/explain-anomalies-batch', [AiController::class, 'explainAnomaliesBatch']);
        });

        Route::middleware('admin')->prefix('admin')->group(function (): void {
            Route::get('/overview', [AdminController::class, 'overview']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{user}/data', [AdminController::class, 'userData']);
            Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole']);
            Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);
            Route::get('/suggestions', [AdminController::class, 'suggestions']);
            Route::patch('/suggestions/{suggestion}', [AdminController::class, 'updateSuggestion']);
            Route::get('/notifications', [AdminController::class, 'notifications']);
            Route::post('/notifications', [AdminController::class, 'storeNotification']);
            Route::get('/invite-codes', [InviteController::class, 'index']);
            Route::post('/invite-codes', [InviteController::class, 'store']);
            Route::post('/invite-codes/generate', [InviteController::class, 'generate']);
            Route::delete('/invite-codes/{inviteCode}', [InviteController::class, 'destroy']);
        });
    });
});
