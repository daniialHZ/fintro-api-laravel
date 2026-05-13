<?php

namespace App\Http\Controllers\Api;

use App\Models\InviteCode;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteController extends BaseApiController
{
    public function __construct(private readonly InviteService $inviteService)
    {
    }

    public function validateCode(Request $request): JsonResponse
    {
        $payload = $request->validate(['code' => ['required', 'string', 'min:4', 'max:50']]);

        return response()->json($this->inviteService->validateInviteCode($payload['code']));
    }

    public function index(Request $request): JsonResponse
    {
        $includeUsed = filter_var($request->query('include_used', false), FILTER_VALIDATE_BOOLEAN);
        $query = InviteCode::query()->orderByDesc('created_at');
        if (! $includeUsed) {
            $query->where('is_active', true);
        }

        return response()->json($query->get()->map(fn (InviteCode $code) => $this->serializeInvite($code))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:50'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string'],
        ]);

        $invite = $this->inviteService->createInviteCode([
            ...$payload,
            'created_by' => $this->currentUser($request)->id,
        ]);

        return response()->json($this->serializeInvite($invite), 201);
    }

    public function generate(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $invite = $this->inviteService->createInviteCode([
            'code' => $this->inviteService->generateRandomCode(),
            'created_by' => $this->currentUser($request)->id,
            'max_uses' => $payload['max_uses'] ?? 1,
            'expires_days' => $payload['expires_days'] ?? 30,
        ]);

        return response()->json(['code' => $invite->code, 'message' => 'کد دعوت با موفقیت ایجاد شد']);
    }

    public function destroy(InviteCode $inviteCode): JsonResponse
    {
        $inviteCode->delete();

        return response()->json(null, 204);
    }

    private function serializeInvite(InviteCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'created_by' => $code->created_by,
            'used_by' => $code->used_by,
            'used_at' => $code->used_at?->toISOString(),
            'expires_at' => $code->expires_at?->toISOString(),
            'max_uses' => $code->max_uses,
            'current_uses' => $code->current_uses,
            'is_active' => (bool) $code->is_active,
            'notes' => $code->notes,
            'created_at' => $code->created_at?->toISOString(),
        ];
    }
}
