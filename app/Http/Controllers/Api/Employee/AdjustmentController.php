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
            'corrected_time' => 'required|date',
            'reason'         => 'required|string|max:500'
        ]);

        $adj = Adjustment::create([
            'user_id'       => $request->user()->id,
            'corrected_time'=> $request->corrected_time,
            'reason'        => $request->reason,
            'status'        => 'pending'
        ]);

        return response()->json($adj, 201);
    }
}
