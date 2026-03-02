<?php

namespace Database\Seeders;

use App\Models\LeavePolicy;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\VacationRequestService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VacationExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            return;
        }

        $user = User::where('email', 'edudtk7@gmail.com')
            ->orWhere('name', 'EDUARDO CHEFE')
            ->first() ?? User::first();

        if (! $user) {
            return;
        }

        $policy = LeavePolicy::where('company_id', $user->company_id)->first();

        if (! $policy) {
            return;
        }

        /** @var VacationRequestService $service */
        $service = app(VacationRequestService::class);

        $pendingStart = Carbon::now()->addWeeks(2)->startOfWeek();
        $pendingEnd = $pendingStart->copy()->addDays(4);

        $pendingCalc = $service->calculateRequestedDays($user, $pendingStart, $pendingEnd, $policy->counting_method ?? 'calendar_days');

        VacationRequest::firstOrCreate(
            [
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'start_date' => $pendingStart->toDateString(),
                'end_date' => $pendingEnd->toDateString(),
            ],
            [
                'requested_days' => $pendingCalc['count'],
                'counting_method_snapshot' => $policy->counting_method ?? 'calendar_days',
                'status' => 'pending',
                'requested_by' => $user->id,
            ]
        );

        $approvedStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $approvedEnd = $approvedStart->copy()->addDays(4);
        $approvedCalc = $service->calculateRequestedDays($user, $approvedStart, $approvedEnd, $policy->counting_method ?? 'calendar_days');

        $approved = VacationRequest::firstOrCreate(
            [
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'start_date' => $approvedStart->toDateString(),
                'end_date' => $approvedEnd->toDateString(),
                'status' => 'approved',
            ],
            [
                'requested_days' => $approvedCalc['count'],
                'counting_method_snapshot' => $policy->counting_method ?? 'calendar_days',
                'requested_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now()->subMonth(),
            ]
        );

        $service->deleteVacationDays($approved);
        $service->createVacationDays($approved, $approvedCalc['days']);
    }
}
