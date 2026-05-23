<?php

namespace App\Http\Controllers\Api\Employee;

use App\Actions\Timesheet\DisputeTimesheetAction;
use App\Actions\Timesheet\SignTimesheetAction;
use App\Enums\ClosureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DisputeTimesheetRequest;
use App\Http\Requests\SignTimesheetRequest;
use App\Http\Resources\EmployeeTimesheetResource;
use App\Http\Resources\TimesheetDisputeResource;
use App\Models\EmployeeTimesheet;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    public function __construct(
        protected SignTimesheetAction $signTimesheetAction,
        protected DisputeTimesheetAction $disputeTimesheetAction
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $timesheets = EmployeeTimesheet::where('employee_id', $user->id)
            ->whereHas('monthlyClosure', fn ($q) => $q->whereIn('status', [
                ClosureStatus::OPEN->value,
                ClosureStatus::COMPLETED->value,
            ]))
            ->with(['monthlyClosure', 'signatures.signer', 'disputes.resolvedBy'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return EmployeeTimesheetResource::collection($timesheets);
    }

    public function show(Request $request, EmployeeTimesheet $timesheet)
    {
        $this->authorize('view', $timesheet);

        $timesheet->load(['employee', 'signatures.signer', 'disputes.resolvedBy']);

        return new EmployeeTimesheetResource($timesheet);
    }

    public function sign(SignTimesheetRequest $request, EmployeeTimesheet $timesheet)
    {
        $this->authorize('signAsEmployee', $timesheet);

        $this->signTimesheetAction->executeAsEmployee($timesheet, $request->user(), $request);

        $timesheet->load(['employee', 'signatures.signer', 'disputes.resolvedBy']);

        return new EmployeeTimesheetResource($timesheet);
    }

    public function dispute(DisputeTimesheetRequest $request, EmployeeTimesheet $timesheet)
    {
        $this->authorize('dispute', $timesheet);

        $dispute = $this->disputeTimesheetAction->execute(
            $timesheet,
            $request->user(),
            $request->string('reason')
        );

        $dispute->load('resolvedBy');

        return (new TimesheetDisputeResource($dispute))->response()->setStatusCode(201);
    }
}
