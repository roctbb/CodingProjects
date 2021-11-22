<?php

namespace App\Http\Middleware;

use App\Idea;
use App\Project;
use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class HasAccessToIdea
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
        if (Auth::User()->role=='teacher' or Auth::User()->role=='admin') {
            return $next($request);
        }

        if (Auth::User()->role=='student') {
            $user = User::findOrFail(Auth::User()->id);
            $idea = Idea::findOrFail($request->id);

            if ($idea->author->id == $user->id and !$idea->is_approved)
            {
                return $next($request);
            }
        }

        return abort(403);

    }
}
