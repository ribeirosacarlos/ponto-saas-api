<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            $this->seedForCompany($company);
        });
    }

    protected function seedForCompany(Company $company): void
    {
        $templates = [
            [
                'name' => 'Jornada Padrão (Seg–Sex)',
                'is_default' => true,
                'is_flexible' => false,
                'days' => $this->buildWeekSchedule('09:00', '18:00', '13:00', '14:00', 60),
            ],
            [
                'name' => 'Turno Manhã',
                'is_default' => false,
                'is_flexible' => false,
                'days' => $this->buildWeekSchedule('06:00', '14:00', '10:00', '10:15', 15),
            ],
        ];

        foreach ($templates as $template) {
            $times = $this->resolveShiftTimes($template['days']);

            $shift = Shift::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name'       => $template['name'],
                ],
                [
                    'start_time'  => $times['start_time'],
                    'end_time'    => $times['end_time'],
                    'is_flexible' => $template['is_flexible'],
                    'is_default'  => $template['is_default'],
                ]
            );

            $this->syncShiftDays($shift, $template['days']);

            if ($template['is_default']) {
                Shift::where('company_id', $company->id)
                    ->where('id', '!=', $shift->id)
                    ->update(['is_default' => false]);
            }
        }
    }

    protected function buildWeekSchedule(string $start, string $end, string $breakStart, string $breakEnd, int $breakMinutes): array
    {
        $days = [];

        foreach (range(1, 5) as $weekday) {
            $days[] = [
                'weekday' => $weekday,
                'is_working_day' => true,
                'start_time' => $start,
                'end_time' => $end,
                'break_start_time' => $breakStart,
                'break_end_time' => $breakEnd,
                'break_minutes' => $breakMinutes,
            ];
        }

        foreach ([6,7] as $weekday) {
            $days[] = [
                'weekday' => $weekday,
                'is_working_day' => false,
                'start_time' => null,
                'end_time' => null,
                'break_start_time' => null,
                'break_end_time' => null,
                'break_minutes' => null,
            ];
        }

        return $days;
    }

    protected function syncShiftDays(Shift $shift, array $days): void
    {
        foreach ($days as $day) {
            $shift->shiftDays()->updateOrCreate(
                ['weekday' => $day['weekday']],
                [
                    'is_working_day'   => $day['is_working_day'],
                    'start_time'       => $day['is_working_day'] ? $day['start_time'] : null,
                    'end_time'         => $day['is_working_day'] ? $day['end_time'] : null,
                    'break_start_time' => $day['is_working_day'] ? $day['break_start_time'] : null,
                    'break_end_time'   => $day['is_working_day'] ? $day['break_end_time'] : null,
                    'break_minutes'    => $day['is_working_day'] ? $day['break_minutes'] : null,
                ]
            );
        }
    }

    protected function resolveShiftTimes(array $days): array
    {
        $workingDays = collect($days)->filter(fn ($day) => $day['is_working_day']);

        if ($workingDays->isEmpty()) {
            return ['start_time' => null, 'end_time' => null];
        }

        return [
            'start_time' => $workingDays->pluck('start_time')->sort()->first(),
            'end_time'   => $workingDays->pluck('end_time')->sort()->last(),
        ];
    }
}
