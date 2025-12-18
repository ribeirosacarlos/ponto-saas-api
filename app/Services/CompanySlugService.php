<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Str;

class CompanySlugService
{
    public function generate(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Company::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter += 1;
        }

        return $slug;
    }
}
