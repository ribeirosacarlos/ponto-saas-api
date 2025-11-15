<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasUuid;
use App\Traits\CompanyScoped;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasUuid, CompanyScoped;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
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

    public function hasRole($role): bool
    {
        $roles = $this->roles->pluck('name')->toArray();

        if (is_array($role)) {
            return count(array_intersect($role, $roles)) > 0;
        }

        return in_array($role, $roles);
    }
}
