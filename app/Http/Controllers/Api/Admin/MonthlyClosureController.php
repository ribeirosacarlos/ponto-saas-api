<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Timesheet\CloseMonthAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseMonthRequest;
use App\Http\Resources\MonthlyClosureResource;
use App\Models\MonthlyClosure;
use App\Models\User;
use Illuminate\Http\Request;

class MonthlyClosureController extends Controller
{
    public function __construct(
        protected CloseMonthAction $closeMonthAction
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', MonthlyClosure::class);

        $closures = MonthlyClosure::withCount('timesheets')
            ->with(['closedBy', 'employee'])
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month')
            ->paginate($request->integer('per_page', 20));

        return MonthlyClosureResource::collection($closures);
    }

    public function store(CloseMonthRequest $request)
    {
        $this->authorize('create', MonthlyClosure::class);

        $employee = User::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($request->input('employee_id'))
            ->whereHas('roles', fn ($query) => $query->where('name', 'employee'))
            ->firstOrFail();

        $closure = $this->closeMonthAction->execute(
            $request->user(),
            $employee,
            $request->integer('reference_year'),
            $request->integer('reference_month')
        );

        $closure->load(['closedBy', 'employee']);
        $closure->loadCount('timesheets');

        return (new MonthlyClosureResource($closure))->response()->setStatusCode(201);
    }

    public function show(MonthlyClosure $closure)
    {
        $this->authorize('view', $closure);

        $closure->load(['closedBy', 'employee'])->loadCount(['timesheets']);

        return new MonthlyClosureResource($closure);
    }
}
