<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;

class UserShiftResolver
{
    /**
     * @param  User  $user
     * @return array{shift: ?Shift, assignment: ?UserShift}
     */
    public function resolve(User $user): array
    {
        $assignment = $user->userShifts()
            ->active()
            ->with('shift.shiftDays')
            ->orderByDesc('start_date')
            ->first();

        $shift = $assignment?->shift;

        if (! $shift && $user->company_id) {
            $shift = Shift::where('company_id', $user->company_id)
                ->where('is_default', true)
                ->with('shiftDays')
                ->first();
        }

        $shift?->loadMissing(['shiftDays' => function ($query) {
            $query->orderBy('weekday');
        }]);

        return [
            'shift' => $shift,
            'assignment' => $assignment,
        ];
    }
}
