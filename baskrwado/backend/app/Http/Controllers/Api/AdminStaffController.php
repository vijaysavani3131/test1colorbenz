<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminStaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->attributes->get('admin_user');
        $query = AdminUser::query()->orderBy('name');
        if (!$actor->canManageStaff()) {
            $query->whereKey($actor->id);
        }

        return response()->json([
            'data' => $query->get()->map(fn (AdminUser $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'role' => $user->role,
                'active' => $user->active,
                'timezone' => $user->timezone,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('admin_user');
        abort_unless($actor->canManageStaff(), 403, 'Only owners and admins can manage staff.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:admin_users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'role' => ['required', Rule::in(['owner','admin','agent','reviewer'])],
        ]);

        $user = AdminUser::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'active' => true,
            'timezone' => 'Asia/Kolkata',
        ]);

        return response()->json(['message' => 'Staff user created.', 'id' => $user->id], 201);
    }

    public function update(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $actor */
        $actor = $request->attributes->get('admin_user');
        abort_unless($actor->canManageStaff(), 403, 'Only owners and admins can manage staff.');

        $staff = AdminUser::query()->findOrFail($staffId);
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['owner','admin','agent','reviewer'])],
            'active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:10', 'max:200'],
        ]);

        if ($staff->id === $actor->id && array_key_exists('active', $validated) && !$validated['active']) {
            abort(422, 'You cannot deactivate your own account.');
        }

        if (isset($validated['password']) && $validated['password'] !== '') {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $staff->fill($validated)->save();
        if (array_key_exists('active', $validated) && !$staff->active) {
            $staff->tokens()->delete();
        }

        return response()->json(['message' => 'Staff user updated.']);
    }
}
