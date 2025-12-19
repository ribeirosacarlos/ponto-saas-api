<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasUuid;
use App\Traits\CompanyScoped;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'invited_at',
        'password_set_at',
        'invite_code_hash',
        'invite_expires_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
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

    public function vacationRequests()
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function vacationDays()
    {
        return $this->hasMany(VacationDay::class);
    }

    public function userLeavePolicies()
    {
        return $this->hasMany(UserLeavePolicy::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function hasRole($role): bool
    {
        $roles = $this->roles->pluck('name')->toArray();

        if (is_array($role)) {
            return count(array_intersect($role, $roles)) > 0;
        }

        return in_array($role, $roles);
    }

    public function assignRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->first();

        if ($roleModel) {
            $this->roles()->syncWithoutDetaching([$roleModel->id]);
        }
    }

    public function syncRoles(array $roles): void
    {
        $roleNames = Arr::wrap($roles);

        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();

        if (! empty($roleIds)) {
            $this->roles()->sync($roleIds);
        }
    }
}
