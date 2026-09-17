<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'string', Rule::in(['Asia/Kolkata','UTC'])],
        ]);

        $user->fill($validated)->save();
        return response()->json(['message' => 'Profile updated.', 'user' => $this->payload($user)]);
    }

    public function password(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
        ]);

        abort_unless(Hash::check($validated['current_password'], $user->password), 422, 'Current password is incorrect.');
        $user->password = $validated['password'];
        $user->save();
        $user->tokens()->where('id', '!=', optional($request->attributes->get('admin_token'))->id)->delete();

        return response()->json(['message' => 'Password changed. Other sessions were signed out.']);
    }

    public function preferences(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $validated = $request->validate([
            'new_case' => ['required', 'boolean'],
            'assigned_case' => ['required', 'boolean'],
            'urgent_case' => ['required', 'boolean'],
            'payment_received' => ['required', 'boolean'],
            'customer_update' => ['required', 'boolean'],
        ]);

        $user->notification_preferences = $validated;
        $user->save();

        return response()->json([
            'message' => 'Notification preferences updated.',
            'preferences' => $notifications->preferences($user),
        ]);
    }

    private function payload(AdminUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'role' => $user->role,
            'timezone' => $user->timezone,
        ];
    }
}
