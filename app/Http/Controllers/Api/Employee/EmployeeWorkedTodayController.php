<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeWorkedTodayResource;
use App\Services\TimeEntry\WorkedTodayService;
use Illuminate\Http\Request;

class EmployeeWorkedTodayController extends Controller
{
    public function __construct(
        protected WorkedTodayService $workedTodayService
    ) {
    }

    public function show(Request $request)
    {
        // Retorna o resumo das horas trabalhadas hoje, considerando pausas e turno.
        $result = $this->workedTodayService->getWorkedToday($request->user());

        return new EmployeeWorkedTodayResource($result);
    }
}
