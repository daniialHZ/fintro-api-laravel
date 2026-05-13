<?php

namespace App\Http\Controllers\Api;

use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $sources = Source::query()
            ->orderByDesc('is_default')
            ->orderBy('name_fa')
            ->get();

        return response()->json($sources->map(fn (Source $source) => $this->serialize($source))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_fa' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string'],
        ]);

        if (Source::query()->where('name', $payload['name'])->exists()) {
            return response()->json(['detail' => 'Source already exists'], 400);
        }

        $source = Source::query()->create([
            ...$payload,
            'icon' => $payload['icon'] ?? '🏦',
            'is_custom' => true,
            'is_default' => false,
        ]);

        return response()->json($this->serialize($source), 201);
    }

    public function update(Request $request, Source $source): JsonResponse
    {
        if (! $source->is_custom) {
            return response()->json(['detail' => 'Source not found or cannot edit default sources'], 404);
        }

        $payload = $request->validate([
            'name_fa' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
        ]);

        $source->fill($payload);
        $source->save();

        return response()->json($this->serialize($source));
    }

    public function destroy(Source $source): JsonResponse
    {
        if (! $source->is_custom) {
            return response()->json(['detail' => 'Source not found or cannot delete default sources'], 404);
        }

        $source->delete();

        return response()->json(null, 204);
    }

    private function serialize(Source $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'name_fa' => $source->name_fa,
            'icon' => $source->icon,
            'is_custom' => (bool) $source->is_custom,
            'is_default' => (bool) $source->is_default,
            'created_at' => $source->created_at?->toISOString(),
        ];
    }
}
