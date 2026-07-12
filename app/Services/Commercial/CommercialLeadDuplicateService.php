<?php

namespace App\Services\Commercial;

use App\Models\CommercialLead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CommercialLeadDuplicateService
{
    /**
     * Procura leads existentes que compartilhem whatsapp, website ou nome da
     * empresa. Sinais fracos: não bloqueiam a criação, apenas sinalizam.
     */
    public function findDuplicates(array $data, ?string $ignoreLeadId = null): Collection
    {
        $query = CommercialLead::query()->where(function ($query) use ($data) {
            $hasCondition = false;

            foreach (['whatsapp', 'website', 'company_name'] as $field) {
                if (! empty($data[$field])) {
                    $hasCondition
                        ? $query->orWhere($field, $data[$field])
                        : $query->where($field, $data[$field]);

                    $hasCondition = true;
                }
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });

        if ($ignoreLeadId) {
            $query->where('id', '!=', $ignoreLeadId);
        }

        return $query->limit(5)->get();
    }

    /**
     * Bloqueia a criação/atualização quando já existe lead com o mesmo
     * e-mail, telefone (normalizado) ou local do Google Maps (place_id).
     *
     * @throws ValidationException
     */
    public function assertNoBlockingDuplicates(array $data, ?string $ignoreLeadId = null): void
    {
        $conflicts = [];

        if (! empty($data['email'])) {
            $email = strtolower(trim($data['email']));

            if ($this->columnValueExists('email', $email, $ignoreLeadId)) {
                $conflicts['email'] = 'Já existe um lead cadastrado com este e-mail.';
            }
        }

        if (! empty($data['phone'])) {
            $normalizedPhone = CommercialLead::normalizePhone($data['phone']);

            if ($normalizedPhone && $this->columnValueExists('phone_normalized', $normalizedPhone, $ignoreLeadId)) {
                $conflicts['phone'] = 'Já existe um lead cadastrado com este telefone.';
            }
        }

        if (! empty($data['google_maps_place_id'])) {
            $placeId = trim($data['google_maps_place_id']);

            if ($this->columnValueExists('google_maps_place_id', $placeId, $ignoreLeadId)) {
                $conflicts['google_maps_place_id'] = 'Já existe um lead cadastrado para este local do Google Maps.';
            }
        }

        if (! empty($conflicts)) {
            throw ValidationException::withMessages($conflicts);
        }
    }

    private function columnValueExists(string $column, string $value, ?string $ignoreLeadId): bool
    {
        return CommercialLead::query()
            ->where($column, $value)
            ->when($ignoreLeadId, fn ($query) => $query->where('id', '!=', $ignoreLeadId))
            ->exists();
    }
}
