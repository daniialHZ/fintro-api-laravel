<?php

namespace App\Http\Controllers\Api;

use App\Models\DebtPerson;
use App\Models\DebtTransaction;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DebtController extends BaseApiController
{
    private const TYPES = [
        'credit',
        'debt',
        'credit_given',
        'credit_received',
        'debt_taken',
        'debt_paid',
        'adjustment_positive',
        'adjustment_negative',
    ];

    public function index(Request $request): JsonResponse
    {
        $userId = $this->currentUser($request)->id;

        $people = DebtPerson::query()
            ->with(['owner', 'sharedWithUser'])
            ->where(function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->orWhere(function ($inner) use ($userId): void {
                        $inner->where('shared_with_user_id', $userId)
                            ->where('share_status', 'accepted');
                    });
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->withSum(['transactions as balance' => fn ($query) => $query->where('status', 'approved')], 'signed_amount')
            ->withCount(['transactions as transactions_count' => fn ($query) => $query->where('status', 'approved')])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $latestTransactions = DebtTransaction::query()
            ->with(['requester', 'approver'])
            ->whereIn('debt_person_id', $people->pluck('id'))
            ->where('status', 'approved')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique('debt_person_id')
            ->keyBy('debt_person_id');

        return response()->json($people->map(fn (DebtPerson $person) => $this->serializePerson(
            $person,
            $userId,
            $latestTransactions->get($person->id)
        ))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'share_identifier' => ['nullable', 'string', 'max:200'],
            'initial_type' => ['nullable', Rule::in(['credit', 'debt'])],
            'initial_amount' => ['nullable', 'numeric', 'gte:0'],
            'initial_date' => ['nullable', 'date'],
            'initial_description' => ['nullable', 'string'],
        ]);

        $currentUser = $this->currentUser($request);
        $shareUser = $this->findShareUser($payload['share_identifier'] ?? $payload['name'], $currentUser->id);

        $person = DB::transaction(function () use ($payload, $currentUser, $shareUser): DebtPerson {
            $person = DebtPerson::query()->create([
                'user_id' => $currentUser->id,
                'shared_with_user_id' => $shareUser?->id,
                'share_status' => $shareUser ? 'pending' : null,
                'share_requested_at' => $shareUser ? now() : null,
                'name' => $shareUser ? $this->displayName($shareUser) : $payload['name'],
                'phone' => $shareUser?->phone_number ?? ($payload['phone'] ?? null),
                'notes' => $payload['notes'] ?? null,
            ]);

            $initialAmount = (float) ($payload['initial_amount'] ?? 0);
            if ($initialAmount > 0 && ! empty($payload['initial_type'])) {
                DebtTransaction::query()->create([
                    'user_id' => $currentUser->id,
                    'debt_person_id' => $person->id,
                    'date' => $payload['initial_date'] ?? now()->toDateString(),
                    'type' => $payload['initial_type'],
                    'amount' => $initialAmount,
                    'signed_amount' => $this->signedAmount($payload['initial_type'], $initialAmount),
                    'status' => 'approved',
                    'requested_by_user_id' => $currentUser->id,
                    'approved_by_user_id' => $currentUser->id,
                    'responded_at' => now(),
                    'description' => $payload['initial_description'] ?? null,
                ]);
            }

            if ($shareUser) {
                Notification::query()->create([
                    'user_id' => $shareUser->id,
                    'created_by' => $currentUser->id,
                    'title' => 'درخواست اشتراک دفتر طلب و بدهی',
                    'message' => $this->displayName($currentUser).' می‌خواهد دفتر حساب «'.$person->name.'» را با شما به اشتراک بگذارد.',
                    'type' => 'info',
                    'action_url' => '/debts',
                ]);
            }

            return $person;
        });

        $person->refresh()
            ->load(['owner', 'sharedWithUser'])
            ->loadCount(['transactions as transactions_count' => fn ($query) => $query->where('status', 'approved')])
            ->loadSum(['transactions as balance' => fn ($query) => $query->where('status', 'approved')], 'signed_amount');

        return response()->json($this->serializePerson($person, $currentUser->id), 201);
    }

    public function update(Request $request, DebtPerson $debtPerson): JsonResponse
    {
        if ($debtPerson->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Person not found'], 404);
        }

        $payload = $request->validate([
            'name' => ['nullable', 'string', 'min:1', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $debtPerson->fill($payload);
        $debtPerson->save();
        $debtPerson->refresh()
            ->load(['owner', 'sharedWithUser'])
            ->loadCount(['transactions as transactions_count' => fn ($query) => $query->where('status', 'approved')])
            ->loadSum(['transactions as balance' => fn ($query) => $query->where('status', 'approved')], 'signed_amount');

        return response()->json($this->serializePerson($debtPerson, $this->currentUser($request)->id));
    }

    public function destroy(Request $request, DebtPerson $debtPerson): JsonResponse
    {
        if ($debtPerson->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Person not found'], 404);
        }

        $debtPerson->delete();

        return response()->json(null, 204);
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $this->currentUser($request)->id;
        $balances = DebtPerson::query()
            ->where(function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->orWhere(function ($inner) use ($userId): void {
                        $inner->where('shared_with_user_id', $userId)
                            ->where('share_status', 'accepted');
                    });
            })
            ->withSum(['transactions as balance' => fn ($query) => $query->where('status', 'approved')], 'signed_amount')
            ->get()
            ->map(fn (DebtPerson $person) => $this->viewerBalance($person, $userId));

        $totalCredit = (float) $balances->filter(fn (float $balance) => $balance > 0)->sum();
        $totalDebt = (float) abs($balances->filter(fn (float $balance) => $balance < 0)->sum());

        return response()->json([
            'total_credit' => $totalCredit,
            'total_debt' => $totalDebt,
            'net_balance' => $totalCredit - $totalDebt,
            'people_count' => $balances->count(),
        ]);
    }

    public function transactions(Request $request, DebtPerson $debtPerson): JsonResponse
    {
        if (! $this->canView($debtPerson, $this->currentUser($request)->id)) {
            return response()->json(['detail' => 'Person not found'], 404);
        }

        $transactions = DebtTransaction::query()
            ->with(['requester', 'approver'])
            ->where('debt_person_id', $debtPerson->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json($transactions->map(fn (DebtTransaction $transaction) => $this->serializeTransaction($transaction, $this->currentUser($request)->id))->all());
    }

    public function storeTransaction(Request $request, DebtPerson $debtPerson): JsonResponse
    {
        $currentUser = $this->currentUser($request);
        if (! $this->canView($debtPerson, $currentUser->id)) {
            return response()->json(['detail' => 'Person not found'], 404);
        }

        $payload = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(self::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ]);

        $amount = (float) $payload['amount'];
        $requiresApproval = $debtPerson->share_status === 'accepted' && $debtPerson->shared_with_user_id !== null;
        $status = $requiresApproval ? 'pending' : 'approved';
        $counterpart = $this->counterpartUser($debtPerson, $currentUser->id);
        $transaction = DebtTransaction::query()->create([
            'user_id' => $currentUser->id,
            'debt_person_id' => $debtPerson->id,
            'date' => $payload['date'],
            'type' => $payload['type'],
            'amount' => $amount,
            'signed_amount' => $this->signedAmountForRequester($debtPerson, $currentUser->id, $payload['type'], $amount),
            'status' => $status,
            'requested_by_user_id' => $currentUser->id,
            'approved_by_user_id' => $status === 'approved' ? $currentUser->id : null,
            'responded_at' => $status === 'approved' ? now() : null,
            'description' => $payload['description'] ?? null,
        ]);

        $debtPerson->updated_at = now();
        $debtPerson->save();

        if ($status === 'pending' && $counterpart) {
            Notification::query()->create([
                'user_id' => $counterpart->id,
                'created_by' => $currentUser->id,
                'title' => 'درخواست تایید تراکنش طلب و بدهی',
                'message' => $this->displayName($currentUser).' یک تراکنش '.$this->typeLabel($payload['type']).' برای دفتر «'.$debtPerson->name.'» ثبت کرده است.',
                'type' => 'info',
                'action_url' => '/debts',
            ]);
        }

        $transaction->load(['requester', 'approver']);

        return response()->json($this->serializeTransaction($transaction, $currentUser->id), 201);
    }

    public function respondToTransaction(Request $request, DebtTransaction $debtTransaction): JsonResponse
    {
        $currentUser = $this->currentUser($request);
        $person = $debtTransaction->person;

        if (! $person || ! $this->canView($person, $currentUser->id) || $debtTransaction->status !== 'pending') {
            return response()->json(['detail' => 'Transaction request not found'], 404);
        }

        if ($debtTransaction->requested_by_user_id === $currentUser->id) {
            return response()->json(['detail' => 'Requester cannot approve this transaction'], 403);
        }

        $payload = $request->validate([
            'status' => ['required', Rule::in(['approved', 'declined'])],
        ]);

        $debtTransaction->status = $payload['status'];
        $debtTransaction->approved_by_user_id = $payload['status'] === 'approved' ? $currentUser->id : null;
        $debtTransaction->responded_at = now();
        $debtTransaction->save();

        $person->updated_at = now();
        $person->save();

        if ($debtTransaction->requester) {
            Notification::query()->create([
                'user_id' => $debtTransaction->requester->id,
                'created_by' => $currentUser->id,
                'title' => $payload['status'] === 'approved' ? 'تراکنش تایید شد' : 'تراکنش رد شد',
                'message' => $this->displayName($currentUser).($payload['status'] === 'approved' ? ' تراکنش دفتر طلب و بدهی را تایید کرد.' : ' تراکنش دفتر طلب و بدهی را رد کرد.'),
                'type' => $payload['status'] === 'approved' ? 'success' : 'warning',
                'action_url' => '/debts',
            ]);
        }

        $debtTransaction->load(['requester', 'approver']);

        return response()->json($this->serializeTransaction($debtTransaction, $currentUser->id));
    }

    public function shareRequests(Request $request): JsonResponse
    {
        $items = DebtPerson::query()
            ->with('owner')
            ->where('shared_with_user_id', $this->currentUser($request)->id)
            ->where('share_status', 'pending')
            ->orderByDesc('share_requested_at')
            ->get()
            ->map(fn (DebtPerson $person) => $this->serializeShareRequest($person))
            ->all();

        return response()->json($items);
    }

    public function respondToShare(Request $request, DebtPerson $debtPerson): JsonResponse
    {
        if ($debtPerson->shared_with_user_id !== $this->currentUser($request)->id || $debtPerson->share_status !== 'pending') {
            return response()->json(['detail' => 'Share request not found'], 404);
        }

        $payload = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
        ]);

        $debtPerson->share_status = $payload['status'];
        $debtPerson->share_responded_at = now();
        $debtPerson->save();

        Notification::query()->create([
            'user_id' => $debtPerson->user_id,
            'created_by' => $this->currentUser($request)->id,
            'title' => $payload['status'] === 'accepted' ? 'اشتراک دفتر پذیرفته شد' : 'اشتراک دفتر رد شد',
            'message' => $this->displayName($this->currentUser($request)).($payload['status'] === 'accepted' ? ' درخواست اشتراک دفتر طلب و بدهی را پذیرفت.' : ' درخواست اشتراک دفتر طلب و بدهی را رد کرد.'),
            'type' => $payload['status'] === 'accepted' ? 'success' : 'warning',
            'action_url' => '/debts',
        ]);

        $debtPerson->load(['owner', 'sharedWithUser'])
            ->loadCount(['transactions as transactions_count' => fn ($query) => $query->where('status', 'approved')])
            ->loadSum(['transactions as balance' => fn ($query) => $query->where('status', 'approved')], 'signed_amount');

        return response()->json($this->serializePerson($debtPerson, $this->currentUser($request)->id));
    }

    public function destroyTransaction(Request $request, DebtTransaction $debtTransaction): JsonResponse
    {
        if ($debtTransaction->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Transaction not found'], 404);
        }

        $person = $debtTransaction->person;
        $debtTransaction->delete();

        if ($person) {
            $person->updated_at = now();
            $person->save();
        }

        return response()->json(null, 204);
    }

    private function signedAmount(string $type, float $amount): float
    {
        return match ($type) {
            'credit', 'credit_given', 'debt_paid', 'adjustment_positive' => $amount,
            default => -$amount,
        };
    }

    private function signedAmountForRequester(DebtPerson $person, int $requesterId, string $type, float $amount): float
    {
        $viewerSignedAmount = $this->signedAmount($type, $amount);

        return $person->user_id === $requesterId ? $viewerSignedAmount : -$viewerSignedAmount;
    }

    private function viewerBalance(DebtPerson $person, int $viewerId): float
    {
        $ownerBalance = (float) ($person->balance ?? 0);

        return $person->user_id === $viewerId ? $ownerBalance : -$ownerBalance;
    }

    private function viewerSignedAmount(DebtTransaction $transaction, int $viewerId): float
    {
        $person = $transaction->person;
        if (! $person) {
            return (float) $transaction->signed_amount;
        }

        return $person->user_id === $viewerId ? (float) $transaction->signed_amount : -((float) $transaction->signed_amount);
    }

    private function canView(DebtPerson $person, int $userId): bool
    {
        return $person->user_id === $userId
            || ($person->shared_with_user_id === $userId && $person->share_status === 'accepted');
    }

    private function counterpartUser(DebtPerson $person, int $userId): ?User
    {
        if ($person->user_id === $userId) {
            return $person->sharedWithUser;
        }

        if ($person->shared_with_user_id === $userId) {
            return $person->owner;
        }

        return null;
    }

    private function findShareUser(?string $identifier, int $currentUserId): ?User
    {
        $value = trim((string) $identifier);
        if ($value === '') {
            return null;
        }

        $normalizedPhone = preg_replace('/[^\d+]/', '', $value);

        return User::query()
            ->where('id', '<>', $currentUserId)
            ->where(function ($query) use ($value, $normalizedPhone): void {
                $query->where('email', mb_strtolower($value));

                if ($normalizedPhone !== '') {
                    $query->orWhere('phone_number', $normalizedPhone)
                        ->orWhere('phone_number', $value);
                }
            })
            ->first();
    }

    private function displayName(User $user): string
    {
        $name = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

        return $name !== '' ? $name : $user->email;
    }

    private function typeLabel(string $type): string
    {
        return $type === 'credit' ? 'طلب' : 'بدهی';
    }

    private function serializePerson(DebtPerson $person, int $viewerId, ?DebtTransaction $latestTransaction = null): array
    {
        $balance = $this->viewerBalance($person, $viewerId);

        return [
            'id' => $person->id,
            'name' => $person->name,
            'phone' => $person->phone,
            'notes' => $person->notes,
            'owner_user_id' => $person->user_id,
            'shared_with_user_id' => $person->shared_with_user_id,
            'share_status' => $person->share_status,
            'share_requested_at' => $person->share_requested_at?->toISOString(),
            'share_responded_at' => $person->share_responded_at?->toISOString(),
            'owner_name' => $person->owner ? $this->displayName($person->owner) : null,
            'shared_with_name' => $person->sharedWithUser ? $this->displayName($person->sharedWithUser) : null,
            'balance' => $balance,
            'status' => $balance > 0 ? 'credit' : ($balance < 0 ? 'debt' : 'settled'),
            'transactions_count' => (int) ($person->transactions_count ?? 0),
            'latest_transaction' => $latestTransaction ? $this->serializeTransaction($latestTransaction, $viewerId) : null,
            'created_at' => $person->created_at?->toISOString(),
            'updated_at' => $person->updated_at?->toISOString(),
        ];
    }

    private function serializeShareRequest(DebtPerson $person): array
    {
        return [
            'id' => $person->id,
            'name' => $person->name,
            'owner_name' => $person->owner ? $this->displayName($person->owner) : null,
            'share_requested_at' => $person->share_requested_at?->toISOString(),
        ];
    }

    private function serializeTransaction(DebtTransaction $transaction, int $viewerId): array
    {
        $signedAmount = $this->viewerSignedAmount($transaction, $viewerId);

        return [
            'id' => $transaction->id,
            'debt_person_id' => $transaction->debt_person_id,
            'date' => $transaction->date?->toDateString(),
            'type' => $signedAmount >= 0 ? 'credit' : 'debt',
            'amount' => (float) $transaction->amount,
            'signed_amount' => $signedAmount,
            'status' => $transaction->status ?? 'approved',
            'requested_by_user_id' => $transaction->requested_by_user_id,
            'approved_by_user_id' => $transaction->approved_by_user_id,
            'requester_name' => $transaction->requester ? $this->displayName($transaction->requester) : null,
            'approver_name' => $transaction->approver ? $this->displayName($transaction->approver) : null,
            'can_respond' => ($transaction->status ?? 'approved') === 'pending' && $transaction->requested_by_user_id !== $viewerId,
            'is_mine' => $transaction->requested_by_user_id === $viewerId,
            'responded_at' => $transaction->responded_at?->toISOString(),
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }
}
