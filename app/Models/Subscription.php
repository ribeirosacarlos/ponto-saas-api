<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'past_due_since',
        'grace_period_days',
        'stripe_customer_id',
        'stripe_subscription_id',
        'metadata',
        'cancel_at_period_end',
        'stripe_price_id',
        'stripe_subscription_item_id',
        'stripe_extra_subscription_item_id',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'past_due_since' => 'datetime',
        'grace_period_days' => 'integer',
        'metadata' => 'array',
        'cancel_at_period_end' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::TRIALING;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PAST_DUE;
    }

    public function isCanceled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }

    public function isInGracePeriod(): bool
    {
        if (! $this->past_due_since || ($this->grace_period_days ?? 0) <= 0) {
            return false;
        }

        $deadline = $this->past_due_since->copy()->addDays($this->grace_period_days);

        return now()->lessThanOrEqualTo($deadline);
    }

    public function isBlocked(): bool
    {
        return $this->status === SubscriptionStatus::PAST_DUE && ! $this->isInGracePeriod();
    }
}
