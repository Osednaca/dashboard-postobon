<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CampaignDeviceBusyException extends ValidationException
{
    public function __construct()
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('target_devices', 'Un dispositivo está procesando contenido. Reintenta cuando termine.');
        parent::__construct($validator);
    }
}
