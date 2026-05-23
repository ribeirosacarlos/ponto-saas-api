<?php

namespace App\Models;

use App\Enums\ClosureStatus;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyClosure extends Model
{
    use HasFactory, HasUuids, CompanyScoped;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'closed_by',
        'reference_year',
        'reference_month',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'status' => ClosureStatus::class,
        'closed_at' => 'datetime',
        'reference_year' => 'integer',
        'reference_month' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(EmployeeTimesheet::class, 'monthly_closure_id');
    }

    public function isProcessing(): bool
    {
        return $this->status === ClosureStatus::PROCESSING;
    }

    public function isOpen(): bool
    {
        return $this->status === ClosureStatus::OPEN;
    }

    public function isCompleted(): bool
    {
        return $this->status === ClosureStatus::COMPLETED;
    }
}
