<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAreaRequest extends StoreAreaRequest
{
    public function rules(): array
    {
        $area = $this->route('area');
        $areaId = is_string($area) ? $area : $area?->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('areas', 'name')
                    ->where(fn ($query) => $query->where('company_id', $this->user()?->company_id))
                    ->ignore($areaId),
            ],
        ];
    }
}
