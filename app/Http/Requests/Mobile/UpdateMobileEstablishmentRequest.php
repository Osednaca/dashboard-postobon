<?php

namespace App\Http\Requests\Mobile;

use App\Http\Requests\StoreEstablishmentRequest;

class UpdateMobileEstablishmentRequest extends StoreEstablishmentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach (['business_type_id', 'name', 'address'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
