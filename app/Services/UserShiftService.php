<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserShiftService
{
    public function assign(User $user, Shift $shift, ?Carbon $startDate = null): UserShift
    {
        $start = ($startDate ?? Carbon::today())->toDateString();

        return DB::transaction(function () use ($user, $shift, $start) {
            $overlaps = $user->userShifts()
                ->where(function ($query) use ($start) {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', $start);
                })
                ->orderByDesc('start_date')
                ->get();

            foreach ($overlaps as $assignment) {
                if (
                    $assignment->shift_id === $shift->id
                    && $assignment->start_date === $start
                    && is_null($assignment->end_date)
                ) {
                    return $assignment;
                }

                if ($assignment->start_date > $start) {
                    $assignment->delete();
                    continue;
                }

                $assignment->update([
                    'end_date' => Carbon::parse($start)->subDay()->toDateString(),
                ]);
            }

            return $user->userShifts()->create([
                'company_id' => $user->company_id,
                'shift_id'   => $shift->id,
                'start_date' => $start,
                'end_date'   => null,
            ]);
        });
    }

    public function assignDefaultIfAvailable(User $user): ?UserShift
    {
        if (! $user->company_id) {
            return null;
        }

        $shift = Shift::where('company_id', $user->company_id)
            ->where('is_default', true)
            ->first();

        if (! $shift) {
            return null;
        }

        return $this->assign($user, $shift);
    }
}
