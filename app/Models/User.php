<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasUuid;
use App\Traits\CompanyScoped;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid, CompanyScoped, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (User $user) {
            $user->roles()->detach();
        });
    }

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'area_id',
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

    protected $appends = [
        'role',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function managedAreas()
    {
        return $this->belongsToMany(Area::class, 'area_user_management', 'user_id', 'area_id')
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
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

    public function commercialAffiliate()
    {
        return $this->hasOne(CommercialAffiliate::class, 'user_id');
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

    public function getRoleAttribute()
    {
        $role = $this->roles->first();

        if (! $role) {
            return null;
        }

        return $role->only(['id', 'name', 'display_name']);
    }
}
