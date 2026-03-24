<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\User;
use App\Observers\CompanyObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;
use Illuminate\Support\Facades\URL;

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
        User::observe(UserObserver::class);

        RateLimiter::for('public-company-registration', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-company-registration';

            return Limit::perMinute(5)->by($ip);
        });

        RateLimiter::for('public-billing-checkout-session', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-billing-checkout-session';

            return Limit::perMinute(10)->by($ip);
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
