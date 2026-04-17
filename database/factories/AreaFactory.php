<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'name' => 'Area ' . Str::upper(Str::random(4)),
        ];
    }
}
