<?php

namespace App\Services\Mobile;

use App\Support\MobileApi;
use Illuminate\Http\Exceptions\HttpResponseException;

class OnboardingProvisioningProfile
{
    public function get(): array
    {
        $domain = (string) config('mobile_onboarding.cloud_domain', '');
        $bind = (bool) config('mobile_onboarding.requires_bind', false);
        $code = (string) config('mobile_onboarding.advertisers_code', '');
        $validDomain = preg_match('/\A[a-z0-9][a-z0-9.-]*:[0-9]{1,5}\z/i', $domain) === 1;
        $port = $validDomain ? (int) substr($domain, strrpos($domain, ':') + 1) : 0;
        $validCode = $code !== '' && strlen($code) <= 128 && ! preg_match('/[&=\x00-\x1F\x7F]/', $code);

        if (! config('mobile_onboarding.enabled') || ! $validDomain || $port < 1 || $port > 65535
            || ($bind && (! config('mobile_onboarding.public_enrollment_identifier') || ! $validCode))) {
            throw new HttpResponseException(MobileApi::error(
                'onboarding_unconfigured',
                'La configuración de instalación aún no está habilitada. Contacta al administrador.',
                503,
            ));
        }

        $profile = [
            'enabled' => true,
            'version' => 1,
            'tcp_port' => 9910,
            'cloud_domain' => $domain,
            'requires_bind' => $bind,
        ];
        if ($bind) {
            $profile['advertisers_code'] = $code;
        }

        return $profile;
    }
}
