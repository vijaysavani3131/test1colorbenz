<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\AdminNotificationService;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'department' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'string', Rule::in(['Asia/Kolkata', 'UTC'])],
            'language' => ['sometimes', 'string', Rule::in(['en', 'hi', 'gu'])],
            'bio' => ['nullable', 'string', 'max:1200'],
        ]);

        $user->fill($validated)->save();

        return response()->json(['message' => 'Profile updated.', 'user' => $this->payload($user)]);
    }

    public function avatar(Request $request, ImageCompressionService $images): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $file = $validated['avatar'];
        $bytes = file_get_contents($file->getRealPath());
        abort_if($bytes === false, 422, 'Unable to read profile image.');

        $mime = strtolower((string) ($file->getMimeType() ?: 'image/jpeg'));
        $optimized = $images->compress($bytes, $mime, ImageCompressionService::TARGET_BYTES);
        $extension = match ($optimized['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = "admin-avatars/{$user->id}.{$extension}";
        $disk = Storage::disk('private');

        if ($user->avatar_path && $user->avatar_path !== $path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }

        $disk->put($path, $optimized['bytes']);
        $user->forceFill([
            'avatar_path' => $path,
            'avatar_mime' => $optimized['mime'],
        ])->save();

        return response()->json([
            'message' => 'Profile image updated.',
            'user' => $this->payload($user),
            'image' => [
                'size_bytes' => $optimized['size_bytes'],
                'compressed' => $optimized['compressed'],
            ],
        ]);
    }

    public function avatarView(Request $request): Response
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $disk = Storage::disk('private');
        abort_unless($user->avatar_path && $disk->exists($user->avatar_path), 404, 'Profile image not found.');

        return response($disk->get($user->avatar_path), 200, [
            'Content-Type' => $user->avatar_mime ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $request->attributes->get('admin_user');
        $disk = Storage::disk('private');

        if ($user->avatar_path && $disk->exists($user->avatar_path)) {
            $disk->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null, 'avatar_mime' => null])->save();

        return response()->json(['message' => 'Profile image removed.', 'user' => $this->payload($user)]);
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
            'department' => $user->department,
            'role' => $user->role,
            'timezone' => $user->timezone ?: 'Asia/Kolkata',
            'language' => $user->language ?: 'en',
            'bio' => $user->bio,
            'avatar_present' => filled($user->avatar_path),
            'avatar_mime' => $user->avatar_mime,
        ];
    }
}
