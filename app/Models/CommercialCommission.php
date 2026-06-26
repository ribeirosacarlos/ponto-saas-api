<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialCommission extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'affiliate_id',
        'lead_id',
        'customer_id',
        'invoice_id',
        'commission_plan_id',
        'base_amount',
        'commission_percentage',
        'commission_amount',
        'month_number',
        'status',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'month_number' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('commissions');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(CommercialAffiliate::class, 'affiliate_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CommercialLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommercialCommissionPlan::class, 'commission_plan_id');
    }
}
