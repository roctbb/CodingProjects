<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        setlocale(LC_TIME, 'ru_RU.UTF-8');
        \Carbon\Carbon::setLocale('ru');
        Blade::withoutDoubleEncoding();

        /*\DB::listen(function ($query) {
            print(
                $query->sql."<br>"
            );
        });*/
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //Recaptcha service
        app()->singleton('App\Services\Recaptcha', function () {
            return new \App\Services\Recaptcha();
        });
        app()->singleton('App\Services\EmailVerify', function () {
            return new \App\Services\EmailVerify();
        });
    }
}
