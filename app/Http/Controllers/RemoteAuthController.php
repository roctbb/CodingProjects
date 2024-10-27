<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RemoteAuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function remoteAuth(Request $request)
    {
        $url = $request->get('redirect_url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(422, "Invalid or no url provided");
        }


        $userId = Auth::user()->id;
        $role = Auth::user()->role;
        $name = Auth::user()->name;

        $token = \Firebase\JWT\JWT::encode([
            'id' => $userId,
            'role' => $role,
            'name' => $name,
            'iat' => time(),
        ], config('auth.jwt_secret'), 'HS256');

        return redirect()->to($url . '?token=' . $token, 302, [], true);
    }

}
