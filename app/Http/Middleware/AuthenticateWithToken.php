<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->cookie('auth_token');

        if (! $token) {
            return response()->json(['detail' => 'Authentication required'], 401);
        }

        $user = User::query()->where('auth_token', $token)->first();

        if (! $user) {
            return response()->json(['detail' => 'Invalid token'], 401);
        }

        $request->attributes->set('current_user', $user);

        return $next($request);
    }
}
