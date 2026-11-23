<?php

namespace App\Exceptions;

use App\Support\ServiceLoginReturn;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation', 'secrets'];

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        if ($request->isMethod('POST') && $request->is('login') && !$request->expectsJson()
            && ($issuer = ServiceLoginReturn::issuerUrl($request)) !== null) {
            if ($exception instanceof TokenMismatchException) {
                // Another tab may have signed in and rotated CSRF. Discard the POST and recheck the session.
                return redirect()->to($issuer, 303);
            }
            if ($exception instanceof ValidationException) {
                $exception->redirectTo(route('login', ['service_login' => $request->query('service_login')]));
            }
        }
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Требуется авторизация.'], 401);
        }

        $callback = $request->query('redirect_url');
        if ($request->routeIs('service.jwt') && is_string($callback) && $callback !== '' && strlen($callback) <= 2048) {
            return redirect()->to(ServiceLoginReturn::loginUrl($callback));
        }

        return redirect()->guest(route('login'));
    }
}
