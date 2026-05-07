<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'start' => 'nullable|date',
            'end'   => 'nullable|date',
            'scope' => 'nullable|in:national,regional,local,company',
        ]);

        $query = Holiday::where('company_id', $request->user()->company_id);

        if ($request->filled('start')) {
            $query->where('date', '>=', $request->input('start'));
        }

        if ($request->filled('end')) {
            $query->where('date', '<=', $request->input('end'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->input('scope'));
        }

        return $query->orderBy('date')->paginate($request->integer('per_page', 50));
    }

    public function store(StoreHolidayRequest $request)
    {
        $holiday = Holiday::create([
            'company_id' => $request->user()->company_id,
            'date'       => $request->date,
            'name'       => $request->name,
            'scope'      => $request->scope ?? 'national',
        ]);

        return response()->json($holiday, 201);
    }

    public function show(Request $request, Holiday $holiday)
    {
        $this->authorizeCompany($request, $holiday);

        return $holiday;
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $this->authorizeCompany($request, $holiday);

        $holiday->update($request->validated());

        return $holiday;
    }

    public function destroy(Request $request, Holiday $holiday)
    {
        $this->authorizeCompany($request, $holiday);

        $holiday->delete();

        return response()->json(['message' => 'Feriado removido.']);
    }

    protected function authorizeCompany(Request $request, Holiday $holiday): void
    {
        if ($holiday->company_id !== $request->user()->company_id) {
            abort(403, 'Feriado não pertence à empresa atual.');
        }
    }
}
