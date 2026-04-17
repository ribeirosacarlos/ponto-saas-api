<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $query = Area::where('company_id', $request->user()->company_id)
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate((int) min(max($request->integer('per_page', 20), 1), 100));
    }

    public function store(StoreAreaRequest $request)
    {
        $area = Area::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->input('name'),
        ]);

        return response()->json($area, 201);
    }

    public function show(Request $request, Area $area)
    {
        $this->authorizeCompany($request, $area);

        return $area;
    }

    public function update(UpdateAreaRequest $request, Area $area)
    {
        $this->authorizeCompany($request, $area);

        $area->update($request->validated());

        return $area->fresh();
    }

    public function destroy(Request $request, Area $area)
    {
        $this->authorizeCompany($request, $area);

        $area->delete();

        return response()->json(['message' => 'Área removida.']);
    }

    protected function authorizeCompany(Request $request, Area $area): void
    {
        if ((string) $area->company_id !== (string) $request->user()->company_id) {
            abort(403, 'Área não pertence à empresa atual.');
        }
    }
}
