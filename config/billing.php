<?php

return [
    'default_plan_slug' => env('BILLING_DEFAULT_PLAN_SLUG', 'free'),
    'grace_period_days_default' => env('BILLING_GRACE_PERIOD_DAYS_DEFAULT', 7),
    'trial_days_default' => env('BILLING_TRIAL_DAYS_DEFAULT', 14),
];
