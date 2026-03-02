<?php

namespace App\Http;

use App\Http\Middleware\HasAccessToCourse;
use App\Http\Middleware\HasAccessToIdea;
use App\Http\Middleware\HasAccessToProject;
use App\Http\Middleware\HasAccessToStep;
use App\Http\Middleware\HasAccessToTask;

use App\Http\Middleware\HttpsProtocol;
use App\Http\Middleware\IsCreatorOfProject;

use App\Http\Middleware\SelfAccess;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        HttpsProtocol::class,
        \App\Http\Middleware\TrustProxies::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'teacher' => \App\Http\Middleware\IsTeacher::class,
        'student' => \App\Http\Middleware\IsStudent::class,
        'novice' => \App\Http\Middleware\IsNovice::class,
        'admin' => \App\Http\Middleware\IsAdmin::class,
        'theme_moderator' => \App\Http\Middleware\ThemeModerator::class,
        'gameown' => \App\Http\Middleware\HasAccessToGame::class,
        'gameowncomment' => \App\Http\Middleware\HasAccessToGameComment::class,
        'noysreward' => \App\Http\Middleware\CannotRewardYourself::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'step' => HasAccessToStep::class,
        'course' => HasAccessToCourse::class,
        'task' => HasAccessToTask::class,
        'project' => HasAccessToProject::class,
        'idea' => HasAccessToIdea::class,
        'self' => SelfAccess::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,

    ];
}
