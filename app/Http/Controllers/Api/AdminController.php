<?php

namespace App\Http\Controllers\Api;

use App\Models\AILog;
use App\Models\Category;
use App\Models\Goal;
use App\Models\InviteCode;
use App\Models\Notification;
use App\Models\PortfolioTarget;
use App\Models\Source;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSuggestion;
use App\Models\Wealth;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends BaseApiController
{
    public function overview(): JsonResponse
    {
        $periodStart = CarbonImmutable::now()->subDays(29)->startOfDay();
        $recentUsers = User::query()
            ->withCount(['transactions', 'goals', 'wealthItems', 'portfolioTargets', 'suggestions'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user))
            ->all();

        $recentSuggestions = UserSuggestion::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (UserSuggestion $suggestion) => $this->serializeSuggestion($suggestion))
            ->all();

        return response()->json([
            'stats' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
                'transactions' => Transaction::query()->count(),
                'goals' => Goal::query()->count(),
                'wealth_items' => Wealth::query()->count(),
                'portfolio_targets' => PortfolioTarget::query()->count(),
                'suggestions' => UserSuggestion::query()->count(),
                'open_suggestions' => UserSuggestion::query()->where('status', '!=', 'done')->count(),
                'sources' => Source::query()->count(),
                'categories' => Category::query()->count(),
                'invite_codes' => InviteCode::query()->count(),
                'notifications' => Notification::query()->count(),
                'unread_notifications' => Notification::query()->whereNull('read_at')->count(),
                'active_users_24h' => $this->activeUsersCount(now()->subDay()),
                'active_users_7d' => $this->activeUsersCount(now()->subDays(7)),
                'active_users_30d' => $this->activeUsersCount(now()->subDays(30)),
            ],
            'charts' => [
                'registrations' => $this->dailySeries(User::query(), 'created_at', $periodStart),
                'usage_events' => $this->usageSeries($periodStart),
                'active_users' => $this->dailySeries(User::query()->whereNotNull('last_seen_at'), 'last_seen_at', $periodStart),
            ],
            'active_user_info' => [
                'definition' => 'A user is active when their authenticated API activity updated last_seen_at in the selected window.',
                'windows' => [
                    '24h' => $this->activeUsersCount(now()->subDay()),
                    '7d' => $this->activeUsersCount(now()->subDays(7)),
                    '30d' => $this->activeUsersCount(now()->subDays(30)),
                ],
            ],
            'recent_users' => $recentUsers,
            'recent_suggestions' => $recentSuggestions,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', "%{$search}%"))
            ->withCount(['transactions', 'goals', 'wealthItems', 'portfolioTargets', 'suggestions'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user))
            ->all();

        return response()->json($users);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $suggestions = UserSuggestion::query()
            ->with('user')
            ->when(in_array($status, ['new', 'reviewing', 'done'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('message', 'like', "%{$search}%")
                        ->orWhere('page', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (UserSuggestion $suggestion) => $this->serializeSuggestion($suggestion))
            ->all();

        return response()->json($suggestions);
    }

    public function notifications(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $notifications = Notification::query()
            ->with(['user', 'creator'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Notification $notification) => $this->serializeNotification($notification))
            ->all();

        return response()->json($notifications);
    }

    public function storeNotification(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'target' => ['required', 'in:all,user'],
            'user_id' => ['nullable', 'required_if:target,user', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'message' => ['required', 'string', 'min:3', 'max:3000'],
            'type' => ['nullable', 'in:info,success,warning,error'],
            'action_url' => ['nullable', 'string', 'max:255'],
        ]);

        $admin = $this->currentUser($request);
        $type = $payload['type'] ?? 'info';
        $userIds = $payload['target'] === 'all'
            ? User::query()->pluck('id')->all()
            : [(int) $payload['user_id']];

        $created = 0;
        foreach ($userIds as $userId) {
            Notification::query()->create([
                'user_id' => $userId,
                'created_by' => $admin->id,
                'title' => trim($payload['title']),
                'message' => trim($payload['message']),
                'type' => $type,
                'action_url' => $payload['action_url'] ?? null,
            ]);
            $created++;
        }

        return response()->json([
            'message' => 'Notification created',
            'created_count' => $created,
        ], 201);
    }

    public function userData(User $user): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser(
                $user->loadCount(['transactions', 'goals', 'wealthItems', 'portfolioTargets', 'suggestions'])
            ),
            'transactions' => Transaction::query()
                ->where('user_id', $user->id)
                ->with(['source', 'category'])
                ->orderByDesc('date')
                ->limit(25)
                ->get(),
            'goals' => Goal::query()
                ->where('user_id', $user->id)
                ->orderBy('deadline')
                ->limit(25)
                ->get(),
            'wealth' => Wealth::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
            'portfolio_targets' => PortfolioTarget::query()
                ->where('user_id', $user->id)
                ->orderByDesc('percentage')
                ->limit(25)
                ->get(),
            'suggestions' => UserSuggestion::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
                ->map(fn (UserSuggestion $suggestion) => $this->serializeSuggestion($suggestion))
                ->all(),
        ]);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        if ($this->currentUser($request)->id === $user->id && ! $payload['is_admin']) {
            return response()->json(['detail' => 'You cannot remove your own admin access'], 422);
        }

        $user->is_admin = (bool) $payload['is_admin'];
        $user->save();

        return response()->json($this->serializeUser(
            $user->loadCount(['transactions', 'goals', 'wealthItems', 'portfolioTargets', 'suggestions'])
        ));
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        if ($this->currentUser($request)->id === $user->id) {
            return response()->json(['detail' => 'You cannot delete your own account'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    public function updateSuggestion(Request $request, UserSuggestion $suggestion): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'in:new,reviewing,done'],
        ]);

        $suggestion->status = $payload['status'];
        $suggestion->save();

        return response()->json($this->serializeSuggestion($suggestion->load('user')));
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'created_at' => $user->created_at?->toISOString(),
            'last_seen_at' => $user->last_seen_at?->toISOString(),
            'counts' => [
                'transactions' => (int) ($user->transactions_count ?? 0),
                'goals' => (int) ($user->goals_count ?? 0),
                'wealth' => (int) ($user->wealth_items_count ?? 0),
                'portfolio' => (int) ($user->portfolio_targets_count ?? 0),
                'suggestions' => (int) ($user->suggestions_count ?? 0),
            ],
        ];
    }

    private function serializeSuggestion(UserSuggestion $suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'user_id' => $suggestion->user_id,
            'user_email' => $suggestion->user?->email,
            'page' => $suggestion->page,
            'message' => $suggestion->message,
            'status' => $suggestion->status,
            'created_at' => $suggestion->created_at?->toISOString(),
        ];
    }

    private function serializeNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'user_email' => $notification->user?->email,
            'created_by' => $notification->created_by,
            'creator_email' => $notification->creator?->email,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'action_url' => $notification->action_url,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }

    private function activeUsersCount(\DateTimeInterface $since): int
    {
        return User::query()->where('last_seen_at', '>=', $since)->count();
    }

    private function dailySeries(Builder $query, string $column, CarbonImmutable $start): array
    {
        $counts = $query
            ->where($column, '>=', $start)
            ->selectRaw("DATE({$column}) as day, COUNT(*) as count")
            ->groupBy('day')
            ->pluck('count', 'day');

        return $this->fillDailySeries($counts->all(), $start);
    }

    private function usageSeries(CarbonImmutable $start): array
    {
        $eventsByDay = [];

        foreach ([
            [Transaction::class, 'created_at'],
            [Wealth::class, 'created_at'],
            [UserSuggestion::class, 'created_at'],
            [AILog::class, 'created_at'],
        ] as [$model, $column]) {
            $counts = $model::query()
                ->where($column, '>=', $start)
                ->selectRaw("DATE({$column}) as day, COUNT(*) as count")
                ->groupBy('day')
                ->pluck('count', 'day');

            foreach ($counts as $day => $count) {
                $eventsByDay[$day] = ($eventsByDay[$day] ?? 0) + (int) $count;
            }
        }

        return $this->fillDailySeries($eventsByDay, $start);
    }

    private function fillDailySeries(array $counts, CarbonImmutable $start): array
    {
        $series = [];

        for ($date = $start; $date->lte(CarbonImmutable::now()->startOfDay()); $date = $date->addDay()) {
            $day = $date->toDateString();
            $series[] = [
                'date' => $day,
                'count' => (int) ($counts[$day] ?? 0),
            ];
        }

        return $series;
    }
}
