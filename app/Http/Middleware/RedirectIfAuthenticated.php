<?php

namespace App\Http\Middleware;

use App\Support\ServiceLoginReturn;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
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
        if (Auth::guard($guard)->check()) {
            if ($request->is('login', 'auth/silaeder') && ($issuer = ServiceLoginReturn::issuerUrl($request)) !== null) {
                return redirect()->to($issuer);
            }
            return redirect('/home');
        }

        return $next($request);
    }
}
