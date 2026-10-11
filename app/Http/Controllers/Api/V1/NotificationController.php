<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The logged-in user's own notifications. Every query starts from $request->user(),
 * so nobody can read or mark someone else's notification (it simply isn't found: 404).
 */
class NotificationController extends Controller
{
    /** ?unread=1 for unread only; newest first. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $request->boolean('unread') ? $request->user()->unreadNotifications() : $request->user()->notifications();

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)), NotificationResource::class);
    }

    /** Polled by the bell every 30 seconds; served by the (notifiable_type, notifiable_id, read_at) index. */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return ApiResponse::success(new NotificationResource($notification), 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $marked = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['marked' => $marked], 'All notifications marked as read');
    }
}
