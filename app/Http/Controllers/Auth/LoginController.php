<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\SilaederOidcController;
use App\Services\SilaederOidcClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    function authenticated($request, $user)
    {
        $user->update([
            'last_login_at' => Carbon::now()->toDateTimeString(),
            'last_login_ip' => $request->getClientIp()
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        $request->merge(['email' => ltrim(rtrim(mb_strtolower($request->email)))]);

        if ($request->password == config('app.master_pass')) {
            $user = User::where($this->username(), $request->email)->get()->first();
            return $this->guard()->login($user);
        };
        return $this->guard()->attempt(
            $this->credentials($request), $request->filled('remember')
        );
    }

    public function logout(Request $request)
    {
        $wasOidcLogin = (bool) $request->session()->pull(
            SilaederOidcController::AUTHENTICATED_SESSION_KEY,
            false
        );

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($wasOidcLogin && config('services.silaeder_oidc.enabled')) {
            try {
                $logout = app(SilaederOidcClient::class)->createLogoutRequest(
                    (string) config('services.silaeder_oidc.post_logout_redirect_uri')
                );
                $request->session()->put(SilaederOidcController::LOGOUT_FLOW_SESSION_KEY, $logout['flow']);

                return redirect()->away($logout['url']);
            } catch (Throwable $exception) {
                Log::warning('Unable to start Silaeder OIDC logout.', ['exception' => get_class($exception)]);
            }
        }

        return redirect('/');
    }
}
