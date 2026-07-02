<?php

namespace Database\Seeders;

use App\Models\CommercialLeadStep;
use Illuminate\Database\Seeder;

class CommercialLeadStepsSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            [
                'slug' => 'lead-novo',
                'name' => 'Lead novo',
                'description' => 'Empresa encontrada e cadastrada. Salvar dados básicos como nome, cidade, segmento, telefone, WhatsApp, e-mail e site.',
                'position' => 1,
                'default_due_days' => 1,
                'is_final' => false,
            ],
            [
                'slug' => 'whatsapp-1',
                'name' => 'WhatsApp 1',
                'description' => 'Primeiro contato por WhatsApp. Objetivo: abrir conversa e entender como a empresa controla horários hoje.',
                'position' => 2,
                'default_due_days' => 2,
                'is_final' => false,
            ],
            [
                'slug' => 'ligacao-1',
                'name' => 'Ligação 1',
                'description' => 'Primeira ligação comercial. Objetivo: falar com o responsável e entender dores, número de colaboradores e processo atual.',
                'position' => 3,
                'default_due_days' => 3,
                'is_final' => false,
            ],
            [
                'slug' => 'email-1',
                'name' => 'E-mail 1',
                'description' => 'E-mail de reforço profissional. Explicar dor, risco trabalhista, economia de tempo e benefício da Jornafy.',
                'position' => 4,
                'default_due_days' => 4,
                'is_final' => false,
            ],
            [
                'slug' => 'whatsapp-2',
                'name' => 'WhatsApp 2',
                'description' => 'Segundo contato por WhatsApp. Mensagem curta para tentar agendar uma demonstração rápida.',
                'position' => 5,
                'default_due_days' => 5,
                'is_final' => false,
            ],
            [
                'slug' => 'ligacao-2',
                'name' => 'Ligação 2',
                'description' => 'Segunda ligação comercial. Objetivo: agendar demo, desqualificar ou mover para nutrição.',
                'position' => 6,
                'default_due_days' => 15,
                'is_final' => false,
            ],
            [
                'slug' => 'ultima-tentativa',
                'name' => 'Última tentativa',
                'description' => 'Último contato comercial ativo. Se não houver resposta, mover depois para nutrição.',
                'position' => 7,
                'default_due_days' => 1,
                'is_final' => false,
            ],
            [
                'slug' => 'nutricao',
                'name' => 'Nutrição',
                'description' => 'Lead sem resposta ou sem momento de compra. Enviar contato leve mensal com dicas, novidades trabalhistas ou convite para testar.',
                'position' => 8,
                'default_due_days' => 30,
                'is_final' => false,
            ],
            [
                'slug' => 'demo-agendada',
                'name' => 'Demo agendada',
                'description' => 'Lead aceitou reunião. Salvar data da demo, responsável, dor principal e número de colaboradores.',
                'position' => 9,
                'default_due_days' => 0,
                'is_final' => false,
            ],
            [
                'slug' => 'demo-realizada',
                'name' => 'Demo realizada',
                'description' => 'Demonstração feita. Classificar o lead como quente, morno ou frio.',
                'position' => 10,
                'default_due_days' => 0,
                'is_final' => false,
            ],
            [
                'slug' => 'proposta-enviada',
                'name' => 'Proposta enviada',
                'description' => 'Proposta comercial enviada. Acompanhar retorno e preparar follow-up.',
                'position' => 11,
                'default_due_days' => 1,
                'is_final' => false,
            ],
            [
                'slug' => 'negociacao',
                'name' => 'Negociação',
                'description' => 'Lead em negociação. Tratar objeções como preço, decisão com sócio, Excel ou concorrente.',
                'position' => 12,
                'default_due_days' => 7,
                'is_final' => false,
            ],
            [
                'slug' => 'fechado-ganho',
                'name' => 'Fechado ganho',
                'description' => 'Cliente contratado. Iniciar onboarding.',
                'position' => 13,
                'default_due_days' => 0,
                'is_final' => true,
            ],
            [
                'slug' => 'fechado-perdido',
                'name' => 'Fechado perdido',
                'description' => 'Cliente recusou ou não tem fit. Salvar motivo da perda.',
                'position' => 14,
                'default_due_days' => 0,
                'is_final' => true,
            ],
        ];

        foreach ($steps as $step) {
            CommercialLeadStep::updateOrCreate(
                ['slug' => $step['slug']],
                [
                    'name' => $step['name'],
                    'description' => $step['description'],
                    'position' => $step['position'],
                    'default_due_days' => $step['default_due_days'],
                    'is_final' => $step['is_final'],
                    'active' => true,
                ],
            );
        }

        CommercialLeadStep::query()
            ->where(function ($query) use ($steps) {
                $query->whereNotIn('slug', array_column($steps, 'slug'))
                    ->orWhereNull('slug');
            })
            ->update(['active' => false]);
    }
}
