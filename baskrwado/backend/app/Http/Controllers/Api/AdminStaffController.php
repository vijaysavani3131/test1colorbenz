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
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AdminUser::query()->orderBy('name')->get()->map(fn (AdminUser $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
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
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'role' => ['required', Rule::in(['owner','admin','agent','reviewer'])],
        ]);

        $user = AdminUser::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'active' => true,
        ]);

        return response()->json(['message' => 'Staff user created.', 'id' => $user->id], 201);
    }
}
