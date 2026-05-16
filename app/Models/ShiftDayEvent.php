<?php

namespace App\Models;

use App\Services\UserShiftResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDayEvent extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'shift_day_id',
        'kind',
        'expected_time',
        'day_offset',
        'expected_type',
        'sort_order',
    ];

    protected $casts = [
        'day_offset' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $clearCache = function (self $model) {
            $shiftDay = $model->shiftDay ?? ShiftDay::find($model->shift_day_id);
            $shift = $shiftDay ? ($shiftDay->shift ?? Shift::find($shiftDay->shift_id)) : null;
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

    public function shiftDay()
    {
        return $this->belongsTo(ShiftDay::class);
    }
}
