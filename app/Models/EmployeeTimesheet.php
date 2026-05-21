<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Enums\TimesheetStatus;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeTimesheet extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'monthly_closure_id',
        'employee_id',
        'status',
        'snapshot',
        'snapshot_generated_at',
        'pdf_path',
        'pdf_generated_at',
    ];

    protected $casts = [
        'status' => TimesheetStatus::class,
        'snapshot' => 'array',
        'snapshot_generated_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function monthlyClosure(): BelongsTo
    {
        return $this->belongsTo(MonthlyClosure::class, 'monthly_closure_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(TimesheetSignature::class, 'employee_timesheet_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(TimesheetDispute::class, 'employee_timesheet_id');
    }

    public function isReadyForPdf(): bool
    {
        return $this->status === TimesheetStatus::COMPLETED && $this->pdf_path === null;
    }

    public function hasOpenDispute(): bool
    {
        return $this->disputes()
            ->where('status', DisputeStatus::OPEN->value)
            ->exists();
    }
}
