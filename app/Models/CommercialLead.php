<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialLead extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'whatsapp',
        'website',
        'country',
        'city',
        'segment',
        'employees_count',
        'source',
        'affiliate_id',
        'current_step_id',
        'assigned_to_user_id',
        'created_by_user_id',
        'status',
        'priority',
        'score',
        'general_notes',
        'next_action_type',
        'next_action_at',
        'next_action_user_id',
        'converted_at',
        'customer_id',
        'lost_reason',
    ];

    protected $casts = [
        'employees_count' => 'integer',
        'score' => 'integer',
        'next_action_at' => 'datetime',
        'converted_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('leads');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(CommercialAffiliate::class, 'affiliate_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(CommercialLeadStep::class, 'current_step_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function nextActionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'next_action_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CommercialLeadNote::class, 'lead_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(CommercialLeadStepLog::class, 'lead_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialCommission::class, 'lead_id');
    }
}
