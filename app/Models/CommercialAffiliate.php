<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class CommercialAffiliate extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'slug',
        'commission_plan_id',
        'status',
        'password',
        'invite_code_hash',
        'invite_expires_at',
    ];

    protected $hidden = ['password', 'invite_code_hash'];

    protected $casts = [
        'invite_expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('affiliates');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
