<?php

namespace App\Services\Commercial;

use App\Models\CommercialLead;
use Illuminate\Database\Eloquent\Collection;

class CommercialLeadDuplicateService
{
    /**
     * Procura leads existentes que compartilhem email, telefone, whatsapp,
     * website ou nome da empresa. Não bloqueia a criação — apenas sinaliza.
     */
    public function findDuplicates(array $data, ?string $ignoreLeadId = null): Collection
    {
        $query = CommercialLead::query()->where(function ($query) use ($data) {
            $hasCondition = false;

            foreach (['email', 'phone', 'whatsapp', 'website', 'company_name'] as $field) {
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
}
