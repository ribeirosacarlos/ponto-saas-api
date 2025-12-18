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
            ],
            [
                'slug' => 'empresa-alpha',
                'name' => 'Empresa Alpha',
                'email' => 'alpha@empresa.com',
            ],
            [
                'slug' => 'empresa-beta',
                'name' => 'Empresa Beta',
                'email' => 'beta@empresa.com',
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
