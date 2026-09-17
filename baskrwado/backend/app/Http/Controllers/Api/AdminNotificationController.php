<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AdminUser;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request, AdminNotificationService $service): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $items = $user->notifications()->latest()->limit(100)->get();

        return response()->json([
            'data' => $items->map(fn (AdminNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'unread_count' => $items->whereNull('read_at')->count(),
            'preferences' => $service->preferences($user),
        ]);
    }

    public function read(Request $request, int $notificationId): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->forceFill(['read_at' => now()])->save();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
