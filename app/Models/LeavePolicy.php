<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'days_per_year',
        'accrual_rate_per_month',
        'counting_method',
        'allow_carry_over',
        'carry_over_limit_days',
        'effective_from',
    ];

    protected $casts = [
        'days_per_year'          => 'decimal:2',
        'accrual_rate_per_month' => 'decimal:3',
        'allow_carry_over'       => 'boolean',
        'carry_over_limit_days'  => 'decimal:2',
        'effective_from'         => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function userAssignments()
    {
        return $this->hasMany(UserLeavePolicy::class);
    }
}
