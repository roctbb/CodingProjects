<?php

namespace App\Http\Middleware;

use Closure;

class IsNovice extends AccessMiddleware
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
        if (!$this->hasRole('novice')) {
            return $this->forbidden();
        }

        return $next($request);
    }
}
