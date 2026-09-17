<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminApiToken;
use App\Models\AdminUser;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $user = AdminUser::query()->where('email', strtolower($validated['email']))->first();
        abort_unless($user && $user->active && Hash::check($validated['password'], $user->password), 422, 'Email or password is incorrect.');

        $plain = 'bkw_'.Str::random(72);
        $token = $user->tokens()->create([
            'name' => 'admin-web',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(30),
        ]);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $user->tokens()->where('id', '!=', $token->id)->where('expires_at', '<', now()->subDays(1))->delete();

        return response()->json([
            'token' => $plain,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user, $notifications),
        ]);
    }

    public function me(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->attributes->get('admin_user'), $notifications)]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AdminApiToken|null $token */
        $token = $request->attributes->get('admin_token');
        $token?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userPayload(AdminUser $user, AdminNotificationService $notifications): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'role' => $user->role,
            'active' => $user->active,
            'timezone' => $user->timezone ?: 'Asia/Kolkata',
            'notification_preferences' => $notifications->preferences($user),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }
}
