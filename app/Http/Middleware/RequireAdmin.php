<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('current_user');

        if (! $user || ! $user->is_admin) {
            return response()->json(['detail' => 'Admin access required'], 403);
        }

        return $next($request);
    }
}
