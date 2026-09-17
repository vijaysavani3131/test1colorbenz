<?php

namespace App\Http\Middleware;

use App\Models\AdminApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        abort_unless($plain, 401, 'Admin authentication required.');

        $token = AdminApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        abort_unless($token && $token->user && $token->user->active, 401, 'Invalid admin token.');
        abort_if($token->expires_at && $token->expires_at->isPast(), 401, 'Admin token expired.');

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('admin_user', $token->user);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
