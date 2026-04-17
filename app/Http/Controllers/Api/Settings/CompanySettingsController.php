<?php

namespace App\Http\Controllers\Api\Settings;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanySettingsOverviewResource;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use App\Services\CompanySubscriptionService;
use App\Services\ExtraEmployeeChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CompanySettingsController extends Controller
{
    private const WORKDAY_TOLERANCE_MINUTES = 10;

    public function __construct(
        protected CompanySubscriptionService $subscriptionService,
        protected ExtraEmployeeChargeService $extraEmployeeChargeService
    )
    {
    }

    public function overview(Request $request)
    {
        $user = $request->user();
        $company = $user?->company;

        if (! $company) {
            if ($user?->hasRole('super_admin')) {
                return new CompanySettingsOverviewResource($this->buildPlatformAdminPayload($user));
            }

            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $company->loadMissing(['subscription.plan', 'currentPlan']);

        $subscription = $company->subscription;
        $plan = $company->currentPlan ?? $subscription?->plan;
        $subscriptionStatus = $subscription?->status?->value ?? $company->subscription_status;

        $flags = $this->buildFlags($company, $subscriptionStatus);

        $payload = [
            'billing' => [
                'plan' => $this->buildPlanPayload($plan),
                'subscription' => $this->buildSubscriptionPayload($company, $subscription, $subscriptionStatus),
            ],
            'flags' => $flags,
            'links' => $this->buildLinks($flags),
            'company' => $this->buildCompanyPayload($company),
            'workday' => $this->buildWorkdayPayload($company, $plan),
            'security' => $this->buildSecurityPayload($user),
            'usage' => $this->buildUsagePayload($company, $plan),
            'compliance' => $this->buildCompliancePayload($company, $plan),
        ];

        return new CompanySettingsOverviewResource($payload);
    }

    private function buildPlatformAdminPayload($user): array
    {
        return [
            'billing' => [
                'plan' => $this->buildPlanPayload(null),
                'subscription' => [
                    'status' => null,
                    'status_label' => 'N/A',
                    'next_action' => 'NONE',
                    'stripe_customer_id' => null,
                    'stripe_subscription_id' => null,
                    'subscription_status' => null,
                    'trial_ends_at' => null,
                    'trial_days_remaining' => null,
                    'current_period_end' => null,
                    'billing_days_remaining' => null,
                    'subscription_ends_at' => null,
                    'cancel_at_period_end' => null,
                    'canceled_at' => null,
                ],
            ],
            'flags' => [
                'can_access_system' => true,
                'is_trial' => false,
                'is_trial_active' => false,
                'is_subscription_active' => false,
                'requires_action' => false,
                'is_platform_admin' => true,
            ],
            'links' => [
                'checkout_url' => null,
                'customer_portal_url' => null,
            ],
            'company' => [
                'name' => 'Platform',
                'timezone' => config('app.timezone'),
                'country' => null,
                'locale' => config('app.locale'),
                'created_at' => null,
            ],
            'workday' => [
                'default_shift' => null,
                'tolerance_minutes' => self::WORKDAY_TOLERANCE_MINUTES,
                'rounding_minutes' => config('workday.rounding_minutes'),
                'geolocation_enabled' => false,
                'geolocation_required' => false,
                'require_photo' => null,
            ],
            'security' => $this->buildSecurityPayload($user),
            'usage' => [
                'employees' => [
                    'current' => 0,
                    'limit' => null,
                    'over_limit' => false,
                ],
            ],
            'compliance' => [
                'log_retention_days' => null,
                'export_enabled' => false,
            ],
        ];
    }

    private function buildFlags(Company $company, ?string $subscriptionStatus): array
    {
        return [
            'can_access_system' => $this->subscriptionService->canAccessSystem($company),
            'is_trial' => $subscriptionStatus === SubscriptionStatus::TRIALING->value,
            'is_trial_active' => $this->subscriptionService->isTrialActive($company),
            'is_subscription_active' => $this->subscriptionService->hasActiveSubscription($company),
            'requires_action' => $this->requiresAction($subscriptionStatus),
        ];
    }

    private function buildPlanPayload(?Plan $plan): array
    {
        if (! $plan) {
            return [
                'name' => null,
                'slug' => null,
                'price_cents' => null,
                'currency' => null,
                'billing_interval' => null,
                'limits' => [],
            ];
        }

        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval,
            'extra_employee_price_cents' => $plan->extra_employee_price_cents,
            'limits' => $plan->quotas ?? [],
        ];
    }

    private function buildSubscriptionPayload(Company $company, ?Subscription $subscription, ?string $subscriptionStatus): array
    {
        $trialEndsAt = $this->toCarbon($subscription?->trial_ends_at ?? $company->trial_ends_at);
        $currentPeriodEnd = $this->toCarbon($subscription?->current_period_end);
        $subscriptionEndsAt = $subscription && $subscription->cancel_at_period_end && $currentPeriodEnd
            ? $currentPeriodEnd
            : null;

        return [
            'status' => $subscriptionStatus,
            'status_label' => $this->statusLabel($subscriptionStatus),
            'next_action' => $this->nextAction($subscriptionStatus),
            'stripe_customer_id' => $company->stripe_customer_id,
            'stripe_subscription_id' => $subscription?->stripe_subscription_id,
            'subscription_status' => $subscriptionStatus,
            'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            'trial_days_remaining' => $this->calculateDaysRemaining($trialEndsAt),
            'current_period_end' => $currentPeriodEnd?->toIso8601String(),
            'billing_days_remaining' => $this->calculateDaysRemaining($currentPeriodEnd),
            'subscription_ends_at' => $subscriptionEndsAt?->toIso8601String(),
            'cancel_at_period_end' => $subscription?->cancel_at_period_end,
            'canceled_at' => $subscription?->canceled_at?->toIso8601String(),
        ];
    }

    private function buildLinks(array $flags): array
    {
        $baseUrl = $this->resolveFrontendUrl();

        return [
            'checkout_url' => $flags['is_subscription_active'] ? null : $baseUrl . '/billing',
            'customer_portal_url' => ($flags['is_subscription_active'] && $baseUrl)
                ? $baseUrl . '/billing/portal'
                : null,
        ];
    }

    private function buildCompanyPayload(Company $company): array
    {
        return [
            'name' => $company->name,
            'timezone' => $company->timezone ?? config('app.timezone'),
            'country' => $company->country,
            'locale' => $company->locale ?? config('app.locale'),
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }

    private function buildWorkdayPayload(Company $company, ?Plan $plan): array
    {
        $defaultShift = Shift::where('company_id', $company->id)
            ->where('is_default', true)
            ->with(['shiftDays' => function ($query) {
                $query->orderBy('weekday');
            }])
            ->first();

        return [
            'default_shift' => $defaultShift ? [
                'id' => $defaultShift->id,
                'name' => $defaultShift->name,
                'start_time' => $defaultShift->start_time,
                'end_time' => $defaultShift->end_time,
                'days' => $defaultShift->shiftDays->map(function ($day) {
                    return [
                        'weekday' => $day->weekday,
                        'is_working_day' => $day->is_working_day,
                        'start_time' => $day->start_time,
                        'end_time' => $day->end_time,
                        'break_expected' => filled($day->break_start_time) && filled($day->break_end_time),
                    ];
                })->values()->all(),
            ] : null,
            'tolerance_minutes' => self::WORKDAY_TOLERANCE_MINUTES,
            'rounding_minutes' => config('workday.rounding_minutes'),
            'geolocation_enabled' => (bool) ($plan?->hasFeature('geolocation') ?? false),
            'geolocation_required' => (bool) $company->geolocation_required,
            'require_photo' => null,
        ];
    }

    private function buildSecurityPayload(?\Illuminate\Foundation\Auth\User $user): array
    {
        $lastLoginAt = $this->toCarbon(data_get($user, 'last_login_at'));

        return [
            'two_factor_enabled' => data_get($user, 'two_factor_enabled'),
            'last_login_at' => $lastLoginAt?->toIso8601String(),
        ];
    }

    private function buildUsagePayload(Company $company, ?Plan $plan): array
    {
        $employeeCount = $company->billableUsersCount();
        $employeeLimit = data_get($plan?->quotas ?? [], 'max_employees');
        $overLimit = is_numeric($employeeLimit) && $employeeLimit !== null
            && $employeeCount > (int) $employeeLimit;

        return [
            'employees' => [
                'current' => $employeeCount,
                'limit' => $employeeLimit,
                'over_limit' => $overLimit,
            ],
            'extra_employees' => $this->extraEmployeeChargeService->buildOverviewPayload($company),
        ];
    }

    private function buildCompliancePayload(Company $company, ?Plan $plan): array
    {
        return [
            'log_retention_days' => $company->log_retention_days ?? null,
            'export_enabled' => (bool) ($plan?->hasFeature('exports') ?? false),
        ];
    }

    private function calculateDaysRemaining(?Carbon $date): ?int
    {
        if (! $date) {
            return null;
        }

        $remaining = Carbon::now()->diffInDays($date, false);

        return max(0, $remaining);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            SubscriptionStatus::TRIALING->value => 'Trial',
            SubscriptionStatus::ACTIVE->value => 'Active',
            SubscriptionStatus::PAST_DUE->value => 'Payment required',
            SubscriptionStatus::CANCELED->value => 'Canceled',
            default => 'Unknown',
        };
    }

    private function nextAction(?string $status): string
    {
        return match ($status) {
            SubscriptionStatus::PAST_DUE->value => 'UPDATE_PAYMENT_METHOD',
            SubscriptionStatus::CANCELED->value => 'RESUBSCRIBE',
            default => 'NONE',
        };
    }

    private function requiresAction(?string $status): bool
    {
        return in_array($status, [
            SubscriptionStatus::PAST_DUE->value,
            'incomplete',
        ], true);
    }

    private function resolveFrontendUrl(string $path = ''): string
    {
        $base = rtrim(config('app.frontend_url') ?? config('app.url'), '/');
        $relative = ltrim($path, '/');

        return $relative === '' ? $base : $base . '/' . $relative;
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse($value);
    }
}
