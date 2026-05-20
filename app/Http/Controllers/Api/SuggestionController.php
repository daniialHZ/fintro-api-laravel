<?php

namespace App\Http\Controllers\Api;

use App\Models\UserSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'page' => ['nullable', 'string', 'max:120'],
        ]);

        $suggestion = UserSuggestion::query()->create([
            'user_id' => $this->currentUser($request)->id,
            'page' => $payload['page'] ?? null,
            'message' => trim($payload['message']),
        ]);

        return response()->json([
            'message' => 'Suggestion received',
            'suggestion' => [
                'id' => $suggestion->id,
                'page' => $suggestion->page,
                'status' => $suggestion->status,
                'created_at' => $suggestion->created_at?->toISOString(),
            ],
        ], 201);
    }
}
