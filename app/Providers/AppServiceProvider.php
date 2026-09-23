<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\TimeEntry;
use App\Models\User;
use App\Observers\CompanyObserver;
use App\Observers\TimeEntryObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Translatable\Facades\Translatable;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(\App\Services\TenantManager::class, function ($app) {
            return new \App\Services\TenantManager;
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
        // Blog posts are seeded in Spanish first; fall back to 'es' (then any
        // available locale) so pt/en requests don't return empty translatable fields.
        Translatable::fallback(fallbackLocale: 'es', fallbackAny: true);

        Company::observe(CompanyObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        User::observe(UserObserver::class);

        RateLimiter::for('public-company-registration', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-company-registration';

            return [
                $this->withAbuseResponse(Limit::perHour(3)->by('registration:hour:'.$ip), 'public-company-registration'),
                $this->withAbuseResponse(Limit::perDay(10)->by('registration:day:'.$ip), 'public-company-registration'),
            ];
        });

        RateLimiter::for('public-billing-checkout-session', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-billing-checkout-session';

            return Limit::perMinute(10)->by($ip);
        });

        RateLimiter::for('public-leads', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-leads';

            return Limit::perHour(3)->by($ip);
        });

        RateLimiter::for('public-leads-optout', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-leads-optout';

            return Limit::perHour(5)->by($ip);
        });

        RateLimiter::for('public-affiliate-click', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'public-affiliate-click';

            return Limit::perMinute(30)->by($ip);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $ip = $request->ip() ?? $request->header('CF-Connecting-IP') ?? 'auth-login';
            $email = Str::lower((string) $request->input('email'));

            return [
                $this->withAbuseResponse(Limit::perMinute(5)->by('login:identity:'.hash('sha256', $email).'|'.$ip), 'auth-login'),
                $this->withAbuseResponse(Limit::perMinute(20)->by('login:ip:'.$ip), 'auth-login'),
            ];
        });

        RateLimiter::for('auth-recovery', function (Request $request) {
            $ip = $request->ip() ?? 'unknown';
            $emailHash = hash('sha256', Str::lower((string) $request->input('email')));

            return [
                $this->withAbuseResponse(Limit::perMinute(3)->by("recovery:identity:{$emailHash}|{$ip}"), 'auth-recovery'),
                $this->withAbuseResponse(Limit::perMinute(10)->by('recovery:ip:'.$ip), 'auth-recovery'),
            ];
        });

        RateLimiter::for('invite-accept', fn (Request $request) => $this->withAbuseResponse(Limit::perMinute(5)->by('invite:'.$request->ip()), 'invite-accept')
        );

        RateLimiter::for('clock', function (Request $request) {
            $user = $request->user();

            return [
                $this->withAbuseResponse(Limit::perMinute(6)->by('clock:user:'.($user?->id ?? $request->ip())), 'clock'),
                $this->withAbuseResponse(Limit::perMinute(1000)->by('clock:company:'.($user?->company_id ?? $request->ip())), 'clock'),
            ];
        });

        RateLimiter::for('exports', function (Request $request) {
            $user = $request->user();

            return [
                $this->withAbuseResponse(Limit::perMinute(5)->by('export:user:'.($user?->id ?? $request->ip())), 'exports'),
                $this->withAbuseResponse(Limit::perMinute(30)->by('export:company:'.($user?->company_id ?? $request->ip())), 'exports'),
            ];
        });

        RateLimiter::for('email-actions', function (Request $request) {
            $user = $request->user();
            $target = (string) ($request->route('employee') ?? $request->route('id') ?? 'unknown');

            return [
                $this->withAbuseResponse(Limit::perHour(3)->by('email:target:'.hash('sha256', $target)), 'email-actions'),
                $this->withAbuseResponse(Limit::perHour(20)->by('email:actor:'.($user?->id ?? $request->ip())), 'email-actions'),
                $this->withAbuseResponse(Limit::perHour(100)->by('email:company:'.($user?->company_id ?? $request->ip())), 'email-actions'),
            ];
        });

        RateLimiter::for('sensitive-admin', function (Request $request) {
            $user = $request->user();

            return [
                $this->withAbuseResponse(Limit::perMinute(10)->by('sensitive:user:'.($user?->id ?? $request->ip())), 'sensitive-admin'),
                $this->withAbuseResponse(Limit::perMinute(50)->by('sensitive:company:'.($user?->company_id ?? $request->ip())), 'sensitive-admin'),
            ];
        });

        RateLimiter::for('public-read', fn (Request $request) => $this->withAbuseResponse(Limit::perMinute(120)->by('public-read:'.$request->ip()), 'public-read')
        );

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }

    private function withAbuseResponse(Limit $limit, string $scope): Limit
    {
        return $limit->response(function (Request $request, array $headers) use ($scope) {
            Log::warning('security.rate_limit_exceeded', [
                'scope' => $scope,
                'route' => $request->route()?->uri(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'email_hash' => $request->filled('email')
                    ? hash('sha256', Str::lower((string) $request->input('email')))
                    : null,
            ]);

            return response()->json(['message' => 'Muitas tentativas. Tente novamente mais tarde.'], 429, $headers);
        });
    }
}
