<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        // Tidak login / tidak pakai token
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Token tidak punya permission
        if (! $user->tokenCan($ability)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}

