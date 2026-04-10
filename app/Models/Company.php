<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
        'is_blocked',
        'blocked_at',
        'blocked_reason',
        'stripe_customer_id',
        'subscription_status',
        'current_plan_id',
        'timezone',
        'country',
        'locale',
        'geolocation_required',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
        'deleted_at' => 'datetime',
        'current_plan_id' => 'string',
        'subscription_status' => 'string',
        'timezone' => 'string',
        'country' => 'string',
        'locale' => 'string',
        'geolocation_required' => 'boolean',
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

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function plan()
    {
        return $this->hasOneThrough(
            Plan::class,
            Subscription::class,
            'company_id',
            'id',
            'id',
            'plan_id'
        );
    }

    public function currentPlan()
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }
}
