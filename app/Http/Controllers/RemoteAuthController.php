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
        $url = $request->validate(['redirect_url' => 'required|string|max:2048|url:http,https'])['redirect_url'];
        $parts = parse_url($url);
        abort_if(isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']), 422, 'Некорректная ссылка для перехода.');
        $origin = strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
        abort_unless(in_array($origin, config('auth.sso_return_origins', []), true), 403, 'Сервис не подключён к единому входу.');

        $user = Auth::user();

        $token = \Firebase\JWT\JWT::encode([
            'id' => $user->id,
            'role' => $user->role,
            'name' => $user->name,
            'avatar_url' => $user->imageUrl(),
            'iat' => time(),
            'exp' => time() + 120,
            // Legacy consumers decode without an audience argument. Keep that contract
            // while restricting the redirect at issuance and expiring the token quickly.
            'iss' => 'codingprojects', 'service_origin' => $origin, 'purpose' => 'service-login',
            'jti' => (string) \Illuminate\Support\Str::uuid(),
        ], config('auth.jwt_secret'), 'HS256');


        parse_str($parts['query'] ?? '', $query);
        $query['token'] = $token;
        $finalUrl = $origin.($parts['path'] ?? '/').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return redirect()->to($finalUrl, 302, [], true);
    }

}
