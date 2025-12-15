<?php

namespace App\Http\Requests;

class UpdateShiftRequest extends StoreShiftRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['name'] = 'sometimes|string|max:255';
        $rules['days'] = 'sometimes|array|size:7';

        return $rules;
    }
}
