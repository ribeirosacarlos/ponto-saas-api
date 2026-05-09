<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'is_flexible' => 'sometimes|boolean',
            'is_default'  => 'sometimes|boolean',
            'days'        => 'required|array|size:7',
            'days.*.weekday' => 'required|integer|min:1|max:7',
            'days.*.is_working_day' => 'required|boolean',
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i',
            'days.*.scheduled_minutes' => 'nullable|integer|min:0',
            'days.*.break_start_time' => 'nullable|date_format:H:i',
            'days.*.break_end_time' => 'nullable|date_format:H:i',
            'days.*.break_minutes' => 'nullable|integer|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $days = collect($this->input('days', []));

            if ($days->pluck('weekday')->unique()->count() !== $days->count()) {
                $validator->errors()->add('days', 'Cada dia da semana deve ser informado apenas uma vez.');
            }

            foreach ($days as $index => $day) {
                $path = "days.$index";
                $isWorking = (bool) ($day['is_working_day'] ?? false);

                $start = $day['start_time'] ?? null;
                $end = $day['end_time'] ?? null;
                $scheduledMinutes = isset($day['scheduled_minutes']) ? (int) $day['scheduled_minutes'] : null;
                $breakStart = $day['break_start_time'] ?? null;
                $breakEnd = $day['break_end_time'] ?? null;

                if ($isWorking) {
                    if (! $start || ! $end) {
                        $validator->errors()->add("$path.start_time", 'Dias úteis devem possuir horário inicial e final.');
                        continue;
                    }

                    if ($start >= $end) {
                        $validator->errors()->add("$path.start_time", 'O horário inicial deve ser menor que o final.');
                    }

                    if ($scheduledMinutes !== null) {
                        $duration = $this->timeToMinutes($end) - $this->timeToMinutes($start);

                        if ($duration < 0) {
                            $validator->errors()->add("$path.scheduled_minutes", 'A carga horária deve ser compatível com a jornada.');
                        } elseif ($scheduledMinutes > $duration) {
                            $validator->errors()->add("$path.scheduled_minutes", 'A carga horária não pode ser maior que a duração da jornada.');
                        }
                    }
                }

                if ($breakStart && ! $breakEnd) {
                    $validator->errors()->add("$path.break_end_time", 'Informe o horário de término do intervalo.');
                }

                if ($breakEnd && ! $breakStart) {
                    $validator->errors()->add("$path.break_start_time", 'Informe o horário de início do intervalo.');
                }

                if ($breakStart && $breakEnd) {
                    if ($breakStart >= $breakEnd) {
                        $validator->errors()->add("$path.break_start_time", 'O início do intervalo deve ser antes do fim.');
                    }

                    if ($isWorking) {
                        if ($breakStart <= $start || $breakEnd >= $end) {
                            $validator->errors()->add("$path.break_start_time", 'Intervalos devem estar dentro da jornada.');
                        }
                    }
                }
            }
        });
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
