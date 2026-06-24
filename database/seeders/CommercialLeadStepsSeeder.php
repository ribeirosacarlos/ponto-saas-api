<?php

namespace Database\Seeders;

use App\Models\CommercialLeadStep;
use Illuminate\Database\Seeder;

class CommercialLeadStepsSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            'WhatsApp inicial',
            'Ligação 1',
            'E-mail',
            'WhatsApp follow-up',
            'Ligação 2',
            'Demonstração agendada',
            'Proposta enviada',
            'Fechado',
            'Perdido',
            'Nutrição',
        ];

        foreach ($steps as $position => $name) {
            CommercialLeadStep::updateOrCreate(
                ['name' => $name],
                ['position' => $position + 1, 'active' => true],
            );
        }
    }
}
