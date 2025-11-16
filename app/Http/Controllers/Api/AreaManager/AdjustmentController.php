<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Adjustment;

class AdjustmentController extends Controller
{
    public function approve($id, Request $request)
    {
        $adj = Adjustment::findOrFail($id);

        $adj->update([
            'status'      => 'approved',
            'approver_id' => $request->user()->id
        ]);

        return response()->json($adj);
    }

    public function reject($id, Request $request)
    {
        $adj = Adjustment::findOrFail($id);

        $adj->update([
            'status'      => 'rejected',
            'approver_id' => $request->user()->id
        ]);

        return response()->json($adj);
    }
}
