<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class SelfAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $user = Auth::user();

        if ($user && $user->role === 'admin') {
            return $next($request);
        }

        if ($user && (int) $user->id === (int) $request->route('id')) {
            return $next($request);
        }

        abort(403);
    }
}
