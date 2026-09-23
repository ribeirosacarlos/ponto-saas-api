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
                'key' => 'registroHorario',
                'label_pt' => 'Registro de Horário',
                'label_es' => 'Registro horario',
                'label_en' => 'Time tracking',
                'sort_order' => 1,
            ],
            [
                'key' => 'compliance',
                'label_pt' => 'Conformidade / Compliance',
                'label_es' => 'Cumplimiento / Compliance',
                'label_en' => 'Compliance',
                'sort_order' => 2,
            ],
            [
                'key' => 'produto',
                'label_pt' => 'Produto',
                'label_es' => 'Producto',
                'label_en' => 'Product',
                'sort_order' => 3,
            ],
            [
                'key' => 'controle-ponto',
                'label_pt' => 'Controle de Ponto',
                'label_es' => 'Control de fichaje',
                'label_en' => 'Time Tracking',
                'sort_order' => 4,
            ],
            [
                'key' => 'gestao-rh',
                'label_pt' => 'Gestão de RH',
                'label_es' => 'Gestión de RRHH',
                'label_en' => 'HR Management',
                'sort_order' => 5,
            ],
            [
                'key' => 'produtividade',
                'label_pt' => 'Produtividade',
                'label_es' => 'Productividad',
                'label_en' => 'Productivity',
                'sort_order' => 6,
            ],
            [
                'key' => 'folha-ponto',
                'label_pt' => 'Folha de Ponto',
                'label_es' => 'Hoja de fichaje',
                'label_en' => 'Timesheet',
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $category) {
            BlogCategory::updateOrCreate(['key' => $category['key']], $category);
        }
    }
}
