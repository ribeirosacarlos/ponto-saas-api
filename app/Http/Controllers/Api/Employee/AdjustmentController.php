<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Adjustment;
use App\Http\Requests\AdjustmentStoreRequest;

class AdjustmentController extends Controller
{
    public function request(AdjustmentStoreRequest $request)
    {
        $this->authorize('create', Adjustment::class);

        $user = $request->user();

        if (!$user->company_id) {
            return response()->json([
                'message' => 'Usuário sem empresa vinculada (company_id).'
            ], 422);
        }

        $adj = Adjustment::create([
            'company_id'     => $user->company_id,
            'user_id'        => $user->id,
            'corrected_time' => $request->corrected_time,
            'reason'         => $request->reason,
            'status'         => 'pending',
        ]);

        return response()->json($adj, 201);
    }
}
