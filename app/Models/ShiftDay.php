<?php

namespace App\Models;

use App\Services\UserShiftResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDay extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shift_id',
        'weekday',
        'is_working_day',
        'start_time',
        'end_time',
        'scheduled_minutes',
        'break_start_time',
        'break_end_time',
        'break_minutes',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
    ];

    protected static function booted(): void
    {
        $clearCache = function (self $model) {
            $shift = $model->shift ?? Shift::find($model->shift_id);
            if (! $shift) {
                return;
            }

            $shift->userShifts()->pluck('user_id')->each(
                fn ($userId) => UserShiftResolver::forgetUserTodayCache($userId)
            );

            if ($shift->is_default) {
                User::where('company_id', $shift->company_id)->pluck('id')->each(
                    fn ($userId) => UserShiftResolver::forgetUserTodayCache($userId)
                );
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function events()
    {
        return $this->hasMany(ShiftDayEvent::class)->orderBy('sort_order');
    }
}
