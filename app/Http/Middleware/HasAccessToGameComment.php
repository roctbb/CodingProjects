<?php

namespace App\Http\Middleware;

use Closure;

class HasAccessToGameComment
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

        if (\Auth::User()->role=='teacher') {
            return $next($request);
        }

        if (\Auth::User()->role=='student') 
        {

            $comment = \App\GameComments::findOrFail($request->cid);
            if ($comment->user->id == \Auth::User()->id)
            {
                return $next($request);
            }
        }

        return abort(403);
    }
}
