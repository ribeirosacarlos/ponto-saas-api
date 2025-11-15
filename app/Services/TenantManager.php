<?php

namespace App\Services;

use App\Models\Company;

class TenantManager
{
    protected ?Company $tenant = null;

    public function setTenant(?Company $company)
    {
        $this->tenant = $company;
    }

    public function tenant(): ?Company
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant ? $this->tenant->id : null;
    }

    public function exists(): bool
    {
        return !is_null($this->tenant);
    }
}
