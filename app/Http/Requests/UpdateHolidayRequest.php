<?php

namespace App\Http\Requests;

class UpdateHolidayRequest extends StoreHolidayRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['date'] = 'sometimes|date';
        $rules['name'] = 'sometimes|string|max:255';

        return $rules;
    }
}
