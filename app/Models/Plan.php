<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'plan_code',
        'description',
        'price_cents',
        'monthly_price_cents',
        'yearly_price_cents',
        'currency',
        'billing_interval',
        'trial_days',
        'is_active',
        'sort_order',
        'features',
        'quotas',
        'included_employees',
        'extra_employee_price_cents',
        'stripe_price_id',
        'stripe_product_id',
        'stripe_extra_employee_price_id',
        'stripe_extra_employee_product_id',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'monthly_price_cents' => 'integer',
        'yearly_price_cents' => 'integer',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
        'quotas' => 'array',
        'included_employees' => 'integer',
        'extra_employee_price_cents' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $key): bool
    {
        return Arr::has($this->features ?? [], $key);
    }

    public function quota(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->quotas ?? [], $key, $default);
    }
}
