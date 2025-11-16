<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Adjustment;
use App\Http\Requests\AdjustmentStoreRequest;

class AdjustmentController extends Controller
{
    public function request(AdjustmentStoreRequest $request)
    {
        $this->authorize('create', Adjustment::class);

        $request->validate([
            'original_time'  => 'required|date',
            'corrected_time' => 'required|date',
            'reason'         => 'required|string'
        ]);

        $adj = Adjustment::create([
            'user_id'       => $request->user()->id,
            'original_time' => $request->original_time,
            'corrected_time'=> $request->corrected_time,
            'reason'        => $request->reason,
            'status'        => 'pending'
        ]);

        return response()->json($adj, 201);
    }
}
