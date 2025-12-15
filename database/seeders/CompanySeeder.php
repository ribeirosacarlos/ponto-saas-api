<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['slug' => 'empresa-teste'],
            [
                'name'  => 'Empresa Teste',
                'email' => 'contato@empresa.com',
                'plan'  => 'pro',
            ]
        );
    }
}
