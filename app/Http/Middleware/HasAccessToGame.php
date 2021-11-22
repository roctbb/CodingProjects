<?php

namespace App\Http\Middleware;

use Closure;

class HasAccessToGame
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (\Auth::User()->role=='teacher' || \Auth::User()->role=='admin') {
            return $next($request);
        }

        if (\Auth::User()->role=='student') {

            $game = \App\Game::findOrFail($request->id);
            if ($game->user->id == \Auth::User()->id)
            {
                return $next($request);
            }
        }

        return abort(403);
    }
}
