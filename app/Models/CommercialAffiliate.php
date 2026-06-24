<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialAffiliate extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'slug',
        'commission_plan_id',
        'status',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('affiliates');
    }

    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommercialCommissionPlan::class, 'commission_plan_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CommercialLead::class, 'affiliate_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(CommercialAffiliateClick::class, 'affiliate_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialCommission::class, 'affiliate_id');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(CommercialAffiliateBonus::class, 'affiliate_id');
    }
}
