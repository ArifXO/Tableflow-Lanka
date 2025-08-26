<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('role:manager') or multiple roles 'role:manager,kitchen'
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $allowed = collect(explode(',', $roles))->map(fn($r) => trim($r))->filter()->contains($user->role);
        if (!$allowed) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
