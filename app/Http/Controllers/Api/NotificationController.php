<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);

        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Notification $notification) => $this->serializeNotification($notification))
            ->all();

        return response()->json([
            'data' => $notifications,
            'unread_count' => Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => Notification::query()
                ->where('user_id', $this->currentUser($request)->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $this->currentUser($request)->id) {
            return response()->json(['detail' => 'Notification not found'], 404);
        }

        if (! $notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json($this->serializeNotification($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $this->currentUser($request)->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read']);
    }

    protected function serializeNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'created_by' => $notification->created_by,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'action_url' => $notification->action_url,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
