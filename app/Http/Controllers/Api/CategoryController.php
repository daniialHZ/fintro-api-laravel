<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $type = $request->query('type');

        $query = Category::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('is_default', true);
            });

        if ($type) {
            $query->where('type', $type);
        }

        return response()->json($query->get()->map(fn (Category $category) => $this->serialize($category))->all());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string'],
            'name_fa' => ['required', 'string'],
            'icon' => ['nullable', 'string'],
            'type' => ['required', 'string'],
        ]);

        $user = $this->currentUser($request);
        $exists = Category::query()
            ->where('name', $payload['name'])
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['detail' => 'Category already exists'], 400);
        }

        $category = Category::query()->create([
            ...$payload,
            'icon' => $payload['icon'] ?? '💰',
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        return response()->json($this->serialize($category));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if ($category->is_default) {
            return response()->json(['detail' => 'Cannot modify default categories'], 400);
        }
        if ($category->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Category not found'], 404);
        }

        $payload = $request->validate([
            'name_fa' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
        ]);

        $category->fill($payload);
        $category->save();

        return response()->json($this->serialize($category));
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if ($category->is_default) {
            return response()->json(['detail' => 'Cannot delete default categories'], 400);
        }
        if ($category->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Category not found'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    private function serialize(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'name_fa' => $category->name_fa,
            'icon' => $category->icon,
            'type' => $category->type,
            'is_default' => (bool) $category->is_default,
        ];
    }
}
