<?php

namespace App\Http\Middleware;

use App\User;
use Illuminate\Support\Facades\Auth;

abstract class AccessMiddleware
{
    protected function authUser()
    {
        return Auth::user();
    }

    protected function currentUser()
    {
        return User::findOrFail($this->authUser()->id);
    }

    protected function hasRole()
    {
        $roles = func_get_args();

        return in_array($this->authUser()->role, $roles, true);
    }

    protected function forbidden()
    {
        return abort(403);
    }
}
