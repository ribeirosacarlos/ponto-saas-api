<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'slug' => 'empresa-teste',
                'name' => 'Empresa Teste',
                'email' => 'contato@empresa.com',
                'plan' => 'pro',
            ],
            [
                'slug' => 'empresa-alpha',
                'name' => 'Empresa Alpha',
                'email' => 'alpha@empresa.com',
                'plan' => 'enterprise',
            ],
            [
                'slug' => 'empresa-beta',
                'name' => 'Empresa Beta',
                'email' => 'beta@empresa.com',
                'plan' => 'free',
            ],
        ];

        foreach ($companies as $company) {
            if (Company::where('slug', $company['slug'])->exists()) {
                continue;
            }

            Company::factory()->create($company);
        }
    }
}
