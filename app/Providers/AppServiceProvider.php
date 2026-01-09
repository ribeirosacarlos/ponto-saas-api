<?php

namespace App\Providers;

use App\Models\Company;
use App\Observers\CompanyObserver;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(\App\Services\TenantManager::class, function ($app) {
            return new \App\Services\TenantManager();
        });
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret'));
        });
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Company::observe(CompanyObserver::class);
    }
}
