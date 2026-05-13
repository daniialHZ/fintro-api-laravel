<?php

namespace App\Http\Controllers\Api;

use App\Models\Wealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WealthController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Wealth::query()->where('user_id', $this->currentUser($request)->id);
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json($query->orderByDesc('created_at')->get()->map(fn (Wealth $wealth) => $this->serialize($wealth))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request, true);
        $wealth = Wealth::query()->create([
            ...$payload,
            'user_id' => $this->currentUser($request)->id,
        ]);

        return response()->json($this->serialize($wealth), 201);
    }

    public function update(Request $request, Wealth $wealth): JsonResponse
    {
        if ($wealth->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Wealth not found'], 404);
        }

        $wealth->fill($this->validatePayload($request, false));
        $wealth->save();

        return response()->json($this->serialize($wealth));
    }

    public function destroy(Request $request, Wealth $wealth): JsonResponse
    {
        if ($wealth->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Wealth not found'], 404);
        }

        $wealth->delete();

        return response()->json(null, 204);
    }

    public function summary(Request $request): JsonResponse
    {
        $items = Wealth::query()->where('user_id', $this->currentUser($request)->id)->get();
        $total = (float) $items->sum('amount');
        $byType = [];
        foreach ($items as $item) {
            $byType[$item->type] = ($byType[$item->type] ?? 0) + (float) $item->amount;
        }

        return response()->json([
            'total_wealth' => $total,
            'by_type' => $byType,
            'by_currency' => ['IRR' => $total],
        ]);
    }

    public function debugEncryption(Request $request): JsonResponse
    {
        $items = Wealth::query()->where('user_id', $this->currentUser($request)->id)->limit(5)->get();

        return response()->json($items->map(fn (Wealth $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'notes_encrypted_in_db' => $item->notes_encrypted ? mb_substr($item->notes_encrypted, 0, 50) : null,
            'notes_decrypted' => $item->notes,
            'has_encryption' => $item->notes_encrypted !== null && $item->notes_encrypted !== $item->notes,
        ])->all());
    }

    private function validatePayload(Request $request, bool $required): array
    {
        $prefix = $required ? 'required' : 'nullable';

        return $request->validate([
            'name' => [$prefix, 'string', 'min:1', 'max:200'],
            'type' => [$prefix, 'string'],
            'amount' => [$prefix, 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function serialize(Wealth $wealth): array
    {
        return [
            'id' => $wealth->id,
            'user_id' => $wealth->user_id,
            'name' => $wealth->name,
            'type' => $wealth->type,
            'amount' => (float) $wealth->amount,
            'quantity' => $wealth->quantity !== null ? (float) $wealth->quantity : null,
            'unit' => $wealth->unit,
            'purchase_date' => $wealth->purchase_date?->toDateString(),
            'purchase_price' => $wealth->purchase_price !== null ? (float) $wealth->purchase_price : null,
            'notes' => $wealth->notes,
            'created_at' => $wealth->created_at?->toISOString(),
            'updated_at' => $wealth->updated_at?->toISOString(),
        ];
    }
}
