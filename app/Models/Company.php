<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'document',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }

    public function userShifts()
    {
        return $this->hasMany(UserShift::class);
    }

    public function holidays()
    {
        return $this->hasMany(Holiday::class);
    }

    public function leavePolicies()
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function userLeavePolicies()
    {
        return $this->hasMany(UserLeavePolicy::class);
    }

    public function vacationRequests()
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function vacationDays()
    {
        return $this->hasMany(VacationDay::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
