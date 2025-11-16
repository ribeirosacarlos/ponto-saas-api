<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;

class ShiftController extends Controller
{
    public function index()
    {
        return Shift::paginate(20);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
            'is_flexible' => 'nullable|boolean'
        ]);

        $shift = Shift::create([
            'company_id'  => $request->user()->company_id,
            'name'        => $request->name,
            'start_time'  => $request->start_time,
            'end_time'    => $request->end_time,
            'is_flexible' => $request->is_flexible ?? false,
        ]);

        return response()->json($shift, 201);
    }

    public function show($id)
    {
        return Shift::findOrFail($id);
    }

    public function update($id, Request $request)
    {
        $shift = Shift::findOrFail($id);

        $shift->update($request->all());

        return $shift;
    }

    public function destroy($id)
    {
        Shift::findOrFail($id)->delete();
        return response()->json(['message' => 'Deletado']);
    }
}
