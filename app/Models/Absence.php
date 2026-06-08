<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_SICK_LEAVE = 'sick_leave';

    public const TYPE_EXCUSED_ABSENCE = 'excused_absence';

    public const COVERAGE_FULL_DAY = 'full_day';

    public const COVERAGE_HOURS = 'hours';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_RECORDED = 'recorded';

    public const EFFECTIVE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_RECORDED,
    ];

    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_RECORDED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'coverage_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'status',
        'comment',
        'counts_for_accrual',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'canceled_by',
        'canceled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'counts_for_accrual' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'absence_document')->withTimestamps();
    }

    public function isMedicalCertificate(): bool
    {
        return $this->type === self::TYPE_SICK_LEAVE;
    }

    public function isFullDayCoverage(): bool
    {
        return ($this->coverage_type ?? self::COVERAGE_FULL_DAY) === self::COVERAGE_FULL_DAY;
    }

    public function isHoursCoverage(): bool
    {
        return $this->coverage_type === self::COVERAGE_HOURS;
    }
}
