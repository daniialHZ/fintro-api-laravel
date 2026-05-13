<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $page = max((int) $request->query('page', 1), 1);
        $pageSize = min(max((int) $request->query('page_size', 50), 1), 200);
        $sortBy = in_array($request->query('sort_by', 'date'), ['id', 'date', 'amount'], true) ? $request->query('sort_by', 'date') : 'date';
        $sortOrder = $request->query('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Transaction::query()
            ->with(['source', 'category'])
            ->where('user_id', $user->id);

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->query('end_date'));
        }
        if ($request->filled('type') && $request->query('type') !== 'all') {
            $query->where('type', $request->query('type'));
        }
        if ((int) $request->query('source_id') > 0) {
            $query->where('source_id', (int) $request->query('source_id'));
        }
        if ((int) $request->query('category_id') > 0) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        $query->orderBy($sortBy, $sortOrder);
        $totalCount = $query->count();
        $items = $query->forPage($page, $pageSize)->get();
        $totalPages = (int) ceil($totalCount / $pageSize);

        return response()->json([
            'data' => $items->map(fn (Transaction $transaction) => $this->serialize($transaction))->all(),
            'pagination' => [
                'current_page' => $page,
                'page_size' => $pageSize,
                'total_items' => $totalCount,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1,
            ],
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $items = Transaction::query()
            ->with(['source', 'category'])
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('date')
            ->get();

        return response()->json($items->map(fn (Transaction $transaction) => $this->serialize($transaction))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request, true);
        if (! Source::query()->whereKey($payload['source_id'])->exists()) {
            return response()->json(['detail' => 'Invalid source_id'], 400);
        }
        if (! Category::query()->whereKey($payload['category_id'])->exists()) {
            return response()->json(['detail' => 'Invalid category_id'], 400);
        }

        $transaction = Transaction::query()->create([
            ...$payload,
            'user_id' => $this->currentUser($request)->id,
        ]);
        $transaction->load(['source', 'category']);

        return response()->json($this->serialize($transaction), 201);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Transaction not found'], 404);
        }

        $payload = $this->validatePayload($request, false);
        if (isset($payload['source_id']) && ! Source::query()->whereKey($payload['source_id'])->exists()) {
            return response()->json(['detail' => 'Invalid source_id'], 400);
        }
        if (isset($payload['category_id']) && ! Category::query()->whereKey($payload['category_id'])->exists()) {
            return response()->json(['detail' => 'Invalid category_id'], 400);
        }

        $transaction->fill($payload);
        $transaction->save();
        $transaction->load(['source', 'category']);

        return response()->json($this->serialize($transaction));
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Transaction not found'], 404);
        }

        $transaction->delete();

        return response()->json(null, 204);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);
        $file = $request->file('file');
        if (! $file) {
            return response()->json(['detail' => 'No file uploaded'], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            return response()->json(['detail' => 'Invalid file format. Please upload Excel or CSV file.'], 400);
        }

        if ($extension !== 'csv') {
            return response()->json(['detail' => 'Excel import requires a spreadsheet package and is not wired yet. CSV import is supported.'], 501);
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['detail' => 'Unable to read file'], 400);
        }

        $header = null;
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $row);
                continue;
            }

            $item = array_combine($header, $row);
            if (! is_array($item) || empty($item['date']) || empty($item['type']) || empty($item['amount']) || empty($item['source_id']) || empty($item['category_id'])) {
                continue;
            }

            Transaction::query()->create([
                'user_id' => $this->currentUser($request)->id,
                'date' => $item['date'],
                'type' => $item['type'],
                'amount' => (float) $item['amount'],
                'source_id' => (int) $item['source_id'],
                'category_id' => (int) $item['category_id'],
                'description' => $item['description'] ?? null,
            ]);
            $count++;
        }
        fclose($handle);

        return response()->json(['message' => "Successfully imported {$count} transactions", 'count' => $count]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Transaction::query()->where('user_id', $this->currentUser($request)->id)->count(),
        ]);
    }

    public function debugAll(Request $request): JsonResponse
    {
        $items = Transaction::query()
            ->with(['source', 'category'])
            ->where('user_id', $this->currentUser($request)->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json($items->map(fn (Transaction $transaction) => $this->serialize($transaction))->all());
    }

    private function validatePayload(Request $request, bool $required): array
    {
        $prefix = $required ? 'required' : 'nullable';

        return $request->validate([
            'date' => [$prefix, 'date'],
            'type' => [$prefix, 'in:income,expense,transfer'],
            'amount' => [$prefix, 'numeric', 'gt:0'],
            'source_id' => [$prefix, 'integer', 'gt:0'],
            'category_id' => [$prefix, 'integer', 'gt:0'],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function serialize(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'date' => $transaction->date?->toDateString(),
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'source_id' => $transaction->source_id,
            'source_name' => $transaction->source?->name_fa,
            'category_id' => $transaction->category_id,
            'category_name' => $transaction->category?->name_fa,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }
}
