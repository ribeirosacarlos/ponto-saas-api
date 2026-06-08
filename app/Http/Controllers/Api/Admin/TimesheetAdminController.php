<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Timesheet\ResolveDisputeAction;
use App\Actions\Timesheet\SignTimesheetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSignTimesheetRequest;
use App\Http\Requests\ResolveDisputeRequest;
use App\Http\Resources\EmployeeTimesheetResource;
use App\Http\Resources\TimesheetDisputeResource;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\TimesheetDispute;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;

class TimesheetAdminController extends Controller
{
    public function __construct(
        protected SignTimesheetAction $signTimesheetAction,
        protected ResolveDisputeAction $resolveDisputeAction,
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function index(Request $request, MonthlyClosure $closure)
    {
        $this->authorize('view', $closure);

        $timesheets = EmployeeTimesheet::where('monthly_closure_id', $closure->id)
            ->where('company_id', $request->user()->company_id)
            ->with(['employee', 'signatures.signer', 'disputes.resolvedBy'])
            ->orderBy('created_at');

        $this->userVisibilityService->applyToUserOwnedQuery($timesheets, $request->user(), 'employee_id', null);

        $timesheets = $timesheets->paginate($request->integer('per_page', 20));

        return EmployeeTimesheetResource::collection($timesheets);
    }

    public function show(EmployeeTimesheet $timesheet)
    {
        $this->authorize('view', $timesheet);

        $timesheet->load(['employee', 'signatures.signer', 'disputes.resolvedBy']);

        return new EmployeeTimesheetResource($timesheet);
    }

    public function sign(AdminSignTimesheetRequest $request, EmployeeTimesheet $timesheet)
    {
        $this->authorize('signAsManager', $timesheet);

        $this->signTimesheetAction->executeAsManager($timesheet, $request->user(), $request);

        $timesheet->load(['employee', 'signatures.signer', 'disputes.resolvedBy']);

        return new EmployeeTimesheetResource($timesheet);
    }

    public function resolveDispute(ResolveDisputeRequest $request, EmployeeTimesheet $timesheet, TimesheetDispute $dispute)
    {
        $this->authorize('resolveDispute', $timesheet);

        abort_unless(
            (string) $dispute->employee_timesheet_id === (string) $timesheet->id
                && (string) $dispute->company_id === (string) $timesheet->company_id,
            404
        );

        $dispute = $this->resolveDisputeAction->execute(
            $dispute,
            $request->user(),
            $request->string('resolution_note')
        );

        $dispute->load('resolvedBy');

        return new TimesheetDisputeResource($dispute);
    }
}
