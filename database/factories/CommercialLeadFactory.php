<?php

namespace Database\Factories;

use App\Models\CommercialLead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CommercialLeadFactory extends Factory
{
    protected $model = CommercialLead::class;

    public function definition(): array
    {
        $company = 'Empresa '.Str::random(6);

        return [
            'company_name' => $company,
            'contact_name' => fake()->name(),
            'email' => Str::lower(Str::random(8)).'@example.com',
            'phone' => fake()->phoneNumber(),
            'status' => 'new',
            'priority' => 'medium',
            'score' => 0,
        ];
    }
}
