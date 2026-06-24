<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialCommissionPlan extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'commission_type',
        'commission_percentage',
        'recurrence_months',
        'bonus_enabled',
        'bonus_every_clients',
        'bonus_amount',
        'active',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
        'recurrence_months' => 'integer',
        'bonus_enabled' => 'boolean',
        'bonus_every_clients' => 'integer',
        'bonus_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('commission_plans');
    }

    public function affiliates(): HasMany
    {
        return $this->hasMany(CommercialAffiliate::class, 'commission_plan_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialCommission::class, 'commission_plan_id');
    }
}
