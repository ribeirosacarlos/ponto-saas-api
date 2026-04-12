<?php

namespace App\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;

class TimeEntry extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'user_shift_id',
        'clocked_at',
        'type',
        'event_kind',
        'latitude',
        'longitude',
        'source',
        'adjustment_status',
        'adjustment_reason',
        'adjustment_requested_by',
        'adjustment_requested_at',
        'proposed_clocked_at',
        'proposed_type',
        'proposed_latitude',
        'proposed_longitude',
        'proposed_source',
        'adjustment_reviewed_by',
        'adjustment_reviewed_at',
        'adjustment_review_reason',
    ];

    protected $casts = [
        'clocked_at' => 'datetime',
        'proposed_clocked_at' => 'datetime',
        'adjustment_requested_at' => 'datetime',
        'adjustment_reviewed_at' => 'datetime',
    ];

    public function scopeExcludeRejected(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('adjustment_status')
                ->orWhere('adjustment_status', '!=', 'rejected');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function userShift()
    {
        return $this->belongsTo(UserShift::class);
    }

    public function isAdjustmentPending(): bool
    {
        return $this->adjustment_status === 'pending';
    }

    public function hasAnyAdjustment(): bool
    {
        return $this->adjustment_status !== null;
    }
}
