<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production, fail loudly on lazy loading (N+1 queries),
        // mass-assignment mistakes and reads of attributes that were not loaded.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Plain <ul class="pagination"> markup, styled in public/css/app.css.
        Paginator::useBootstrapFour();

        // Login: 5 tries a minute per email and IP (so one person guessing a
        // password is stopped, but a class sharing one router is not), plus a
        // generous per-IP cap against password spraying across many emails.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            return [
                Limit::perMinute(60)->by($request->ip()),
                Limit::perMinute(5)->by(sha1(Str::lower(is_string($email) ? $email : '').'|'.$request->ip())),
            ];
        });

        // Sign up: its own bucket, so it never blocks logins.
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
