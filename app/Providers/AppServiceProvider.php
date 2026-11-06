<?php

namespace App\Providers;

use Carbon\Carbon;
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
        // Pagination memakai markup Bootstrap 5
        Paginator::useBootstrapFive();

        // Nama hari/bulan dalam Bahasa Indonesia
        Carbon::setLocale('id');

        // Saat development: paksa eager loading (cegah N+1 query)
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
