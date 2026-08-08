<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OidcAuthenticationException;
use App\Http\Controllers\Controller;
use App\Notifications\ConfirmSilaederOidcLink;
use App\OidcLinkRequest;
use App\Services\EmailVerify;
use App\Services\SilaederOidcClient;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class SilaederOidcController extends Controller
{
    public const LOGIN_FLOW_SESSION_KEY = 'silaeder_oidc_login_flow';
    public const LOGOUT_FLOW_SESSION_KEY = 'silaeder_oidc_logout_flow';
    public const AUTHENTICATED_SESSION_KEY = 'silaeder_oidc_authenticated';

    private const FLOW_LIFETIME_SECONDS = 600;
    private const ALLOWED_EXTERNAL_ROLES = ['student', 'teacher', 'admin'];
    private const LINK_CONFIRMATION_LIFETIME_MINUTES = 30;

    public function redirectToProvider(Request $request, SilaederOidcClient $oidc)
    {
        return $this->startAuthorization($request, $oidc, 'login');
    }

    public function link(Request $request, SilaederOidcClient $oidc)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['student', 'teacher'], true)) {
            abort(403);
        }

        return $this->startAuthorization($request, $oidc, 'link', $user->id);
    }

    public function callback(Request $request, SilaederOidcClient $oidc)
    {
        $flow = $request->session()->pull(self::LOGIN_FLOW_SESSION_KEY);
        if (!$this->validFlowState($flow, (string) $request->query('state'))) {
            return $this->loginFailure('Сессия входа устарела или была повреждена. Начните вход заново.');
        }

        if ($request->query('error')) {
            $message = $request->query('error') === 'access_denied'
                ? 'Вход через ЛК Силаэдра отменён.'
                : 'ЛК Силаэдра не разрешил вход.';

            return $this->loginFailure($message);
        }

        try {
            $identity = $oidc->authenticate((string) $request->query('code'), $flow);
            $result = DB::transaction(fn () => $this->resolveUser($identity, $flow));
        } catch (OidcAuthenticationException $exception) {
            Log::warning('Silaeder OIDC authentication failed.', ['reason' => $exception->getMessage()]);
            return $this->loginFailure($this->publicErrorMessage($exception));
        } catch (QueryException $exception) {
            Log::warning('Silaeder OIDC user synchronization failed because of a database constraint.');
            return $this->loginFailure('Не удалось связать аккаунт с ЛК Силаэдра. Обратитесь к администратору.');
        } catch (Throwable $exception) {
            Log::warning('Silaeder OIDC authentication failed unexpectedly.', ['exception' => get_class($exception)]);
            return $this->loginFailure('Не удалось войти через ЛК Силаэдра. Попробуйте ещё раз позже.');
        }

        if (isset($result['link_request'])) {
            try {
                $confirmationUrl = URL::temporarySignedRoute(
                    'silaeder.link.confirm',
                    Carbon::now()->addMinutes(self::LINK_CONFIRMATION_LIFETIME_MINUTES),
                    ['token' => $result['token']]
                );
                $result['link_request']->user->notify(new ConfirmSilaederOidcLink($confirmationUrl));
            } catch (Throwable $exception) {
                $result['link_request']->delete();
                Log::warning('Unable to send Silaeder OIDC link confirmation.', [
                    'exception' => get_class($exception),
                ]);

                return $this->loginFailure('Не удалось отправить письмо для подтверждения. Попробуйте ещё раз позже.');
            }

            return redirect('/login')->with(
                'oidc_status',
                'Аккаунт с таким email уже существует. Мы отправили ссылку для подтверждения и привязки.'
            );
        }

        $user = $result['user'];

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::AUTHENTICATED_SESSION_KEY, true);

        $user->forceFill([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        if (($flow['intent'] ?? null) === 'link') {
            return redirect('/insider/profile/' . $user->id . '/edit')
                ->with('status', 'Аккаунт ЛК Силаэдра привязан.');
        }

        return redirect()->intended('/insider/courses');
    }

    public function logoutCallback(Request $request)
    {
        $flow = $request->session()->pull(self::LOGOUT_FLOW_SESSION_KEY);
        if (!$this->validFlowState($flow, (string) $request->query('state'))) {
            return redirect('/login')->with('oidc_error', 'Единый выход завершён с некорректным state.');
        }

        return redirect('/login')->with('oidc_status', 'Вы вышли из приложения и ЛК Силаэдра.');
    }

    public function confirmLink(Request $request, string $token)
    {
        try {
            $user = DB::transaction(function () use ($token) {
                $linkRequest = OidcLinkRequest::where('token_hash', hash('sha256', $token))
                    ->where('expires_at', '>', Carbon::now())
                    ->lockForUpdate()
                    ->first();
                if (!$linkRequest) {
                    throw new OidcAuthenticationException('Ссылка подтверждения недействительна или уже использована.');
                }

                $user = User::whereKey($linkRequest->user_id)->lockForUpdate()->first();
                if (!$user || mb_strtolower(trim($user->email)) !== $linkRequest->email) {
                    throw new OidcAuthenticationException('Email локального аккаунта изменился. Начните вход заново.');
                }

                $linkedUser = User::where('oidc_issuer', $linkRequest->oidc_issuer)
                    ->where('oidc_subject', $linkRequest->oidc_subject)
                    ->lockForUpdate()
                    ->first();
                if ($linkedUser && $linkedUser->id !== $user->id) {
                    throw new OidcAuthenticationException('Этот аккаунт ЛК Силаэдра уже привязан к другому пользователю.');
                }
                if ($user->oidc_subject && (
                    $user->oidc_issuer !== $linkRequest->oidc_issuer
                    || $user->oidc_subject !== $linkRequest->oidc_subject
                )) {
                    throw new OidcAuthenticationException('К локальному аккаунту уже привязан другой аккаунт ЛК.');
                }

                $user->forceFill([
                    'oidc_issuer' => $linkRequest->oidc_issuer,
                    'oidc_subject' => $linkRequest->oidc_subject,
                    'name' => $linkRequest->name,
                    'role' => $linkRequest->role,
                    'email_verified_at' => Carbon::now(),
                    'last_login_at' => Carbon::now(),
                    'last_login_ip' => request()->ip(),
                ])->save();
                $linkRequest->delete();

                return $user;
            });
        } catch (OidcAuthenticationException $exception) {
            return $this->loginFailure($this->publicErrorMessage($exception));
        } catch (Throwable $exception) {
            Log::warning('Unable to confirm Silaeder OIDC account link.', ['exception' => get_class($exception)]);
            return $this->loginFailure('Не удалось связать аккаунты. Начните вход заново.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::AUTHENTICATED_SESSION_KEY, true);

        return redirect('/insider/courses')->with([
            'alert-class' => 'alert-success',
            'alert-destination' => 'head',
            'alert-title' => 'Готово!',
            'alert-text' => 'Email подтверждён, аккаунт ЛК Силаэдра привязан.',
        ]);
    }

    private function startAuthorization(
        Request $request,
        SilaederOidcClient $oidc,
        string $intent,
        ?int $localUserId = null
    ) {
        try {
            $authorization = $oidc->createAuthorizationRequest(
                (string) config('services.silaeder_oidc.redirect_uri')
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to start Silaeder OIDC authentication.', ['exception' => get_class($exception)]);
            return $this->loginFailure('Вход через ЛК Силаэдра временно недоступен.');
        }

        $flow = $authorization['flow'];
        $flow['intent'] = $intent;
        $flow['local_user_id'] = $localUserId;
        $request->session()->put(self::LOGIN_FLOW_SESSION_KEY, $flow);

        return redirect()->away($authorization['url']);
    }

    private function resolveUser(array $identity, array $flow): array
    {
        $userinfo = $identity['userinfo'];
        $externalRole = $userinfo['role'] ?? null;
        if (!is_string($externalRole) || !in_array($externalRole, self::ALLOWED_EXTERNAL_ROLES, true)) {
            throw new OidcAuthenticationException('Роль пользователя из ЛК Силаэдра не поддерживается.');
        }

        $roles = $userinfo['roles'] ?? [];
        if (!is_array($roles) || !in_array($externalRole, $roles, true)) {
            throw new OidcAuthenticationException('OIDC userinfo roles are inconsistent.');
        }
        $role = $externalRole === 'admin' ? 'teacher' : $externalRole;

        $email = mb_strtolower(trim((string) ($userinfo['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new OidcAuthenticationException('ЛК Силаэдра не передал корректный email.');
        }

        $name = trim((string) ($userinfo['name'] ?? $userinfo['preferred_username'] ?? ''));
        if ($name === '') {
            $name = $email;
        }
        $name = Str::limit($name, 255, '');

        $linkedUser = User::where('oidc_issuer', $identity['issuer'])
            ->where('oidc_subject', $identity['subject'])
            ->lockForUpdate()
            ->first();

        if (($flow['intent'] ?? null) === 'link') {
            $localUser = User::whereKey($flow['local_user_id'] ?? 0)->lockForUpdate()->first();
            if (!$localUser || Auth::id() !== $localUser->id) {
                throw new OidcAuthenticationException('Локальная сессия для привязки аккаунта истекла.');
            }
            if ($linkedUser && $linkedUser->id !== $localUser->id) {
                throw new OidcAuthenticationException('Этот аккаунт ЛК Силаэдра уже привязан к другому пользователю.');
            }
            if ($localUser->oidc_subject && (
                $localUser->oidc_issuer !== $identity['issuer']
                || $localUser->oidc_subject !== $identity['subject']
            )) {
                throw new OidcAuthenticationException('К локальному аккаунту уже привязан другой аккаунт ЛК.');
            }

            $user = $localUser;
        } elseif ($linkedUser) {
            $user = $linkedUser;
        } else {
            $emailUser = User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            if ($emailUser) {
                if ($emailUser->oidc_subject) {
                    throw new OidcAuthenticationException('К локальному аккаунту уже привязан другой аккаунт ЛК.');
                }

                OidcLinkRequest::where('expires_at', '<=', Carbon::now())->delete();
                $token = Str::random(64);
                $linkRequest = OidcLinkRequest::create([
                    'user_id' => $emailUser->id,
                    'token_hash' => hash('sha256', $token),
                    'oidc_issuer' => $identity['issuer'],
                    'oidc_subject' => $identity['subject'],
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'expires_at' => Carbon::now()->addMinutes(self::LINK_CONFIRMATION_LIFETIME_MINUTES),
                ]);

                return ['link_request' => $linkRequest, 'token' => $token];
            }

            $user = new User([
                'password' => bcrypt(Str::random(64)),
                'email_verified_at' => ($userinfo['email_verified'] ?? false)
                    ? Carbon::now()
                    : app(EmailVerify::class)->getDate(),
            ]);
        }

        $emailOwner = $user->exists
            && User::whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($user->getKey())->exists();
        $user->forceFill([
            'oidc_issuer' => $identity['issuer'],
            'oidc_subject' => $identity['subject'],
            'name' => $name,
            'role' => $role,
        ]);
        if (!$emailOwner) {
            $user->email = $email;
        }
        if (($userinfo['email_verified'] ?? false) && !$user->email_verified_at) {
            $user->email_verified_at = Carbon::now();
        }
        $user->save();

        return ['user' => $user];
    }

    private function validFlowState($flow, string $state): bool
    {
        return is_array($flow)
            && is_string($flow['state_hash'] ?? null)
            && is_int($flow['created_at'] ?? null)
            && $flow['created_at'] >= time() - self::FLOW_LIFETIME_SECONDS
            && $flow['created_at'] <= time() + 60
            && $state !== ''
            && hash_equals($flow['state_hash'], hash('sha256', $state));
    }

    private function publicErrorMessage(OidcAuthenticationException $exception): string
    {
        $message = $exception->getMessage();
        if (str_starts_with($message, 'ЛК ')
            || str_starts_with($message, 'Только ')
            || str_starts_with($message, 'Этот ')
            || str_starts_with($message, 'Роль ')
            || str_starts_with($message, 'К локальному ')
            || str_starts_with($message, 'Локальный ')
            || str_starts_with($message, 'Ссылка ')
            || str_starts_with($message, 'Email ')
        ) {
            return $message;
        }

        return 'Не удалось проверить ответ ЛК Силаэдра. Начните вход заново.';
    }

    private function loginFailure(string $message)
    {
        if (Auth::check()) {
            return redirect('/insider/profile/' . Auth::id() . '/edit')->with('oidc_error', $message);
        }

        return redirect('/login')->with('oidc_error', $message);
    }
}
