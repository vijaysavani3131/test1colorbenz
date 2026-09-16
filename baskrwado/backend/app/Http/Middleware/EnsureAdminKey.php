<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.baskrwado.admin_api_key');
        $provided = (string) $request->header('X-Admin-Key');

        abort_if($expected === '', 503, 'Admin API key is not configured.');
        abort_unless($provided !== '' && hash_equals($expected, $provided), 401, 'Invalid admin credentials.');

        return $next($request);
    }
}
