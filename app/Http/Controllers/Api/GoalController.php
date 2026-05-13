<?php

namespace App\Http\Controllers\Api;

use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $goals = Goal::query()
            ->where('user_id', $user->id)
            ->offset((int) $request->query('skip', 0))
            ->limit((int) $request->query('limit', 100))
            ->get();

        return response()->json($goals->map(fn (Goal $goal) => $this->serialize($goal))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'current_amount' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['required', 'date'],
        ]);

        $goal = Goal::query()->create([
            ...$payload,
            'current_amount' => $payload['current_amount'] ?? 0,
            'user_id' => $this->currentUser($request)->id,
        ]);

        return response()->json($this->serialize($goal), 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        if ($goal->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Goal not found'], 404);
        }

        $payload = $request->validate([
            'name' => ['nullable', 'string'],
            'target_amount' => ['nullable', 'numeric', 'gt:0'],
            'current_amount' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
        ]);

        $goal->fill($payload);
        $goal->save();

        return response()->json($this->serialize($goal));
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        if ($goal->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Goal not found'], 404);
        }

        $goal->delete();

        return response()->json(null, 204);
    }

    private function serialize(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => (float) $goal->target_amount,
            'current_amount' => (float) $goal->current_amount,
            'deadline' => $goal->deadline?->toDateString(),
        ];
    }
}
