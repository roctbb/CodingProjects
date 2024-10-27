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
        function addQueryParam($url, $key, $value)
        {
            $query = parse_url($url, PHP_URL_QUERY);
            $separator = $query ? '&' : '?';
            return $url . $separator . urlencode($key) . '=' . urlencode($value);
        }

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


        $finalUrl = addQueryParam($url, 'token', $token);

        return redirect()->to($finalUrl, 302, [], true);
    }

}
