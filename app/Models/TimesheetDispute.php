<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetDispute extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_timesheet_id',
        'employee_id',
        'reason',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'status' => DisputeStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function employeeTimesheet(): BelongsTo
    {
        return $this->belongsTo(EmployeeTimesheet::class, 'employee_timesheet_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
