<?php

namespace App\Http\Middleware;

use Closure;

class SelfAccess extends AccessMiddleware
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
        if ($this->hasRole('admin')) {
            return $next($request);
        }

        if ($this->authUser()->id == $request->id) {
            return $next($request);
        }

        return $this->forbidden();

    }
}
