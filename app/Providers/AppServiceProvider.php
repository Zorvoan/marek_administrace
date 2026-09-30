<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

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
    }
}
