<?php

namespace App\Http\Requests;

class UpdateLeavePolicyRequest extends StoreLeavePolicyRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $key => $rule) {
            if (is_array($rule)) {
                $rules[$key] = array_map(function ($item) {
                    return $item === 'required' ? 'sometimes' : $item;
                }, $rule);
            }
        }

        $rules['name'][0] = 'sometimes';

        return $rules;
    }
}
