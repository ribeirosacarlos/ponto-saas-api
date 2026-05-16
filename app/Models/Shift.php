<?php

namespace App\Models;

use App\Services\UserShiftResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Shift extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'is_flexible',
        'is_default',
    ];

    protected $casts = [
        'is_flexible' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        $clearCache = function (self $model) {
            // Invalidate all users explicitly assigned to this shift.
            $model->userShifts()->pluck('user_id')->each(
                fn ($userId) => UserShiftResolver::forgetUserTodayCache($userId)
            );

            // If this is the default shift, any company user without an explicit
            // assignment falls back to it — invalidate all of them too.
            if ($model->is_default) {
                User::where('company_id', $model->company_id)->pluck('id')->each(
                    fn ($userId) => UserShiftResolver::forgetUserTodayCache($userId)
                );
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function shiftDays()
    {
        return $this->hasMany(ShiftDay::class);
    }

    public function userShifts()
    {
        return $this->hasMany(UserShift::class);
    }
}
