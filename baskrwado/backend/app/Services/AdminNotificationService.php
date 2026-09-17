<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AdminUser;
use Illuminate\Support\Collection;

class AdminNotificationService
{
    public const DEFAULT_PREFERENCES = [
        'new_case' => true,
        'assigned_case' => true,
        'urgent_case' => true,
        'payment_received' => true,
        'customer_update' => true,
    ];

    public function preferences(AdminUser $user): array
    {
        return array_replace(self::DEFAULT_PREFERENCES, $user->notification_preferences ?: []);
    }

    public function notify(AdminUser $user, string $type, string $title, string $message, array $data = []): ?AdminNotification
    {
        $preferences = $this->preferences($user);
        if (($preferences[$type] ?? true) !== true) {
            return null;
        }

        return AdminNotification::create([
            'admin_user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function notifyOwnersAndAdmins(string $type, string $title, string $message, array $data = []): Collection
    {
        return AdminUser::query()
            ->where('active', true)
            ->whereIn('role', ['owner', 'admin'])
            ->get()
            ->map(fn (AdminUser $user) => $this->notify($user, $type, $title, $message, $data))
            ->filter()
            ->values();
    }
}
