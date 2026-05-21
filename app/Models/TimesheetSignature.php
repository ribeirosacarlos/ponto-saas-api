<?php

namespace App\Models;

use App\Enums\TimesheetSignatureRole;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetSignature extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_timesheet_id',
        'signer_id',
        'role',
        'signed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'role' => TimesheetSignatureRole::class,
        'signed_at' => 'datetime',
    ];

    public function employeeTimesheet(): BelongsTo
    {
        return $this->belongsTo(EmployeeTimesheet::class, 'employee_timesheet_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
