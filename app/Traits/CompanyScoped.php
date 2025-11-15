<?php

namespace App\Traits;

use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;

trait CompanyScoped
{
    protected static function bootCompanyScoped()
    {
        // Add global scope for SELECT
        static::addGlobalScope('company', function (Builder $builder) {
            $tenant = app(TenantManager::class)->tenant();

            if ($tenant) {
                $builder->where($builder->getModel()->getTable() . '.company_id', $tenant->id);
            }
        });

        // Auto-fill company_id when creating
        static::creating(function ($model) {
            $tenant = app(TenantManager::class)->tenant();
            if ($tenant && empty($model->company_id)) {
                $model->company_id = $tenant->id;
            }
        });
    }
}
