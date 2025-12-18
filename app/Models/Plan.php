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
        'description',
        'price_cents',
        'currency',
        'billing_interval',
        'trial_days',
        'is_active',
        'sort_order',
        'features',
        'quotas',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
        'quotas' => 'array',
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
