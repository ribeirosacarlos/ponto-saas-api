<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'key'        => 'registroHorario',
                'label_pt'   => 'Registro de Horário',
                'label_es'   => 'Registro horario',
                'label_en'   => 'Time tracking',
                'sort_order' => 1,
            ],
            [
                'key'        => 'compliance',
                'label_pt'   => 'Conformidade / Compliance',
                'label_es'   => 'Cumplimiento / Compliance',
                'label_en'   => 'Compliance',
                'sort_order' => 2,
            ],
        ];

        foreach ($categories as $category) {
            BlogCategory::updateOrCreate(['key' => $category['key']], $category);
        }
    }
}
