<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformCompanyStoreRequest;
use App\Http\Requests\PlatformCompanyUpdateRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $status = strtolower($request->input('status', ''));

        $query = Company::with('subscription.plan');

        if ($status === 'deleted') {
            $query = $query->onlyTrashed();
        } else {
            if ($status === 'blocked') {
                $query->where('is_blocked', true);
            } elseif ($status === 'active') {
                $query->where('is_blocked', false);
            }
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = ltrim($sort, '-');
        }

        $allowedSorts = ['name', 'slug', 'created_at', 'updated_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $companies = $query->orderBy($sort, $direction)->paginate($perPage);

        return CompanyResource::collection($companies);
    }

    public function store(PlatformCompanyStoreRequest $request)
    {
        $payload = $request->validated();
        $payload['slug'] = $this->buildSlug($payload['name']);

        $company = Company::create($payload);

        return (new CompanyResource($company))->response()->setStatusCode(201);
    }

    public function show(string $company)
    {
        $company = $this->findWithTrashed($company);

        return new CompanyResource($company);
    }

    public function update(PlatformCompanyUpdateRequest $request, string $company)
    {
        $company = $this->findWithTrashed($company);

        if ($company->trashed()) {
            abort(404);
        }

        $payload = $request->validated();

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->buildSlug($payload['name'], $company->id);
        }

        $company->update($payload);

        return new CompanyResource($company);
    }

    public function destroy(string $company)
    {
        $company = Company::with('subscription.plan')->findOrFail($company);
        $company->delete();

        return response()->noContent();
    }

    public function restore(string $company)
    {
        $company = $this->findWithTrashed($company);

        if (! $company->trashed()) {
            return response()->json(['message' => 'Empresa não está deletada.'], 422);
        }

        $company->restore();

        return new CompanyResource($company);
    }

    public function block(Request $request, string $company)
    {
        $payload = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $company = Company::with('subscription.plan')->findOrFail($company);

        if ($company->is_blocked) {
            return new CompanyResource($company);
        }

        $company->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => $payload['reason'] ?? null,
        ]);

        return new CompanyResource($company);
    }

    public function unblock(string $company)
    {
        $company = Company::findOrFail($company);

        if (! $company->is_blocked) {
            return new CompanyResource($company);
        }

        $company->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);

        return new CompanyResource($company);
    }

    protected function findWithTrashed(string $id): Company
    {
        return Company::withTrashed()->with('subscription.plan')->findOrFail($id);
    }

    protected function buildSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Company::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
