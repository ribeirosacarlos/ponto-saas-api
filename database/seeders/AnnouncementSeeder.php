<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            'Novas orientações para o expediente',
            'Feriado prolongado confirmado',
            'Boas práticas para home office',
            'Agenda de férias do time',
            'Atualização da política interna',
            'Expansão do time de CX',
            'Manutenção programada do sistema',
        ];

        $summaries = [
            'Confira os detalhes sobre a nova rotina de entrada.',
            'Lembrete sobre o feriado nacional desta semana.',
            'Dicas rápidas para manter produtividade fora do escritório.',
            'Planeje suas férias com antecedência.',
            'Reforçamos o uso das ferramentas aprovadas.',
            'Estamos contratando para vagas estratégicas.',
            'Sistema ficará fora do ar por algumas horas.',
        ];

        $bodies = [
            'Nossa equipe precisa seguir o novo fluxo de check-in apresentado no documento interno.',
            'O expediente terá uma pausa na próxima quinta-feira em virtude do feriado.',
            'Aproveite as dicas de ergonomia e gestão do tempo para entregar mais com menos estresse.',
            'Solicitações de férias devem ser aprovadas com, pelo menos, 30 dias de antecedência.',
            'Atualizamos os guias de conduta para alinhar com o crescimento da companhia.',
            'Veja as oportunidades abertas no painel de vagas e compartilhe com seu network.',
            'Agendamos manutenção para aplicar melhorias no desempenho do sistema.',
        ];

        $types = ['general', 'holiday', 'vacation', 'tip'];

        Company::all()->each(function (Company $company) use ($titles, $summaries, $bodies, $types) {
            $admins = $company->users()
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'manager', 'area_manager']))
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $announcements = [];

            for ($i = 0; $i < 10; $i++) {
                $announcements[] = Announcement::create([
                    'company_id' => $company->id,
                    'created_by' => $admins->random()->id,
                    'title' => Arr::random($titles),
                    'summary' => Arr::random($summaries),
                    'body' => Arr::random($bodies),
                    'type' => Arr::random($types),
                    'sent_at' => Carbon::now()
                        ->subDays(rand(0, 30))
                        ->subHours(rand(0, 12))
                        ->subMinutes(rand(0, 59)),
                ]);
            }

            $readUsers = User::where('company_id', $company->id)
                ->inRandomOrder()
                ->take(3)
                ->get();

            foreach ($announcements as $index => $announcement) {
                if ($readUsers->isEmpty() || $index % 2 !== 0) {
                    continue;
                }

                foreach ($readUsers as $user) {
                    AnnouncementRead::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'announcement_id' => $announcement->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'seen_at' => Carbon::now()
                                ->subDays(rand(0, 29))
                                ->subHours(rand(0, 12))
                                ->subMinutes(rand(0, 59)),
                        ]
                    );
                }
            }
        });
    }
}
