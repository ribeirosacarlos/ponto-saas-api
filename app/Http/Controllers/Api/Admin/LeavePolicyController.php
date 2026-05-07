<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeavePolicyRequest;
use App\Http\Requests\UpdateLeavePolicyRequest;
use App\Models\LeavePolicy;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function index(Request $request)
    {
        $policies = LeavePolicy::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json($policies);
    }

    public function store(StoreLeavePolicyRequest $request)
    {
        $user = $request->user();

        $data = $this->preparePayload($request->validated());

        $policy = LeavePolicy::create([
            ...$data,
            'company_id' => $user->company_id,
        ]);

        return response()->json($policy, 201);
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy)
    {
        $this->authorizePolicy($request->user()->company_id, $leavePolicy);

        $data = $this->preparePayload($request->validated(), false);

        if (! empty($data)) {
            $leavePolicy->update($data);
        }

        return response()->json($leavePolicy);
    }

    public function destroy(Request $request, LeavePolicy $leavePolicy)
    {
        $this->authorizePolicy($request->user()->company_id, $leavePolicy);

        $leavePolicy->delete();

        return response()->json(['message' => 'Política removida.']);
    }

    protected function authorizePolicy(string $companyId, LeavePolicy $policy): void
    {
        if ($policy->company_id !== $companyId) {
            abort(403, 'Política não pertence à empresa atual.');
        }
    }

    protected function preparePayload(array $data, bool $isStore = true): array
    {
        if (isset($data['days_per_year']) && ! isset($data['annual_entitlement_days'])) {
            $data['annual_entitlement_days'] = $data['days_per_year'];
        }

        if (! isset($data['annual_entitlement_days']) && $isStore) {
            $data['annual_entitlement_days'] = 30;
        }

        if (! isset($data['accrual_basis']) && $isStore) {
            $data['accrual_basis'] = 'calendar_days';
        }

        if (! isset($data['day_work_threshold_minutes']) && $isStore) {
            $data['day_work_threshold_minutes'] = 1;
        }

        if (isset($data['annual_entitlement_days'])) {
            $data['days_per_year'] = $data['annual_entitlement_days'];
        }

        if (! isset($data['accrual_rate_per_month']) && isset($data['days_per_year'])) {
            $data['accrual_rate_per_month'] = round(($data['days_per_year'] ?? 30) / 12, 3);
        }

        if (isset($data['allow_carry_over'])) {
            $data['allow_carry_over'] = (bool) $data['allow_carry_over'];
        }

        return $data;
    }
}
