<?php

namespace App\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TimeEntry extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'clocked_at',
        'type',
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
        'adjustment_origin_id',
    ];

    protected $casts = [
        'clocked_at' => 'datetime',
        'proposed_clocked_at' => 'datetime',
        'adjustment_requested_at' => 'datetime',
        'adjustment_reviewed_at' => 'datetime',
        'adjustment_origin_id' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adjustmentOrigin()
    {
        return $this->belongsTo(self::class, 'adjustment_origin_id');
    }

    public function adjustmentRequests()
    {
        return $this->hasMany(self::class, 'adjustment_origin_id');
    }

    public function hasPendingAdjustmentRequest(): bool
    {
        return $this->adjustmentRequests()
            ->where('adjustment_status', 'pending')
            ->exists();
    }

    public function isAdjustmentPending(): bool
    {
        return $this->adjustment_status === 'pending';
    }

    public function hasAnyAdjustment(): bool
    {
        return $this->adjustment_status !== null;
    }

    public function applyApprovedAdjustment(self $target = null): void
    {
        $target ??= $this;

        if ($this->proposed_clocked_at) {
            $target->clocked_at = $this->proposed_clocked_at;
        }

        if ($this->proposed_type) {
            $target->type = $this->proposed_type;
        }

        if (! is_null($this->proposed_latitude)) {
            $target->latitude = (string) $this->proposed_latitude;
        }

        if (! is_null($this->proposed_longitude)) {
            $target->longitude = (string) $this->proposed_longitude;
        }

        if ($this->proposed_source) {
            $target->source = $this->proposed_source;
        }

        $this->proposed_clocked_at = null;
        $this->proposed_type = null;
        $this->proposed_latitude = null;
        $this->proposed_longitude = null;
        $this->proposed_source = null;
    }
}
