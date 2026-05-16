<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class UserShiftResolver
{
    /**
     * @param  User  $user
     * @return array{shift: ?Shift, assignment: ?UserShift}
     */
    public function resolve(User $user, ?CarbonImmutable $reference = null): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone', 'UTC');
        $today = ($reference ?? CarbonImmutable::now($timezone))
            ->setTimezone($timezone)
            ->toDateString();

        return Cache::remember(self::cacheKey($user->id, $today), now()->addDays(30), function () use ($user, $today) {
            $assignment = $user->userShifts()
                ->whereDate('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $today);
                })
                ->with([
                    'shift.shiftDays' => function ($query) {
                        $query->orderBy('weekday');
                    },
                    'shift.shiftDays.events' => function ($query) {
                        $query->orderBy('sort_order');
                    },
                ])
                ->orderByDesc('start_date')
                ->first();

            $shift = $assignment?->shift;

            if (! $shift && $user->company_id) {
                $shift = Shift::where('company_id', $user->company_id)
                    ->where('is_default', true)
                    ->with([
                        'shiftDays' => function ($query) {
                            $query->orderBy('weekday');
                        },
                        'shiftDays.events' => function ($query) {
                            $query->orderBy('sort_order');
                        },
                    ])
                    ->first();
            }

            $shift?->loadMissing([
                'shiftDays' => function ($query) {
                    $query->orderBy('weekday');
                },
                'shiftDays.events' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ]);

            return [
                'shift' => $shift,
                'assignment' => $assignment,
            ];
        });
    }

    public static function cacheKey(string $userId, string $date): string
    {
        return "user_shift_resolve:{$userId}:{$date}";
    }

    public static function forgetUserTodayCache(string $userId): void
    {
        Cache::forget(self::cacheKey($userId, now()->toDateString()));
    }
}
