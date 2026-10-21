<?php

namespace App\Services\Mobile;

use App\Rules\FirmwareCloudDomain;
use App\Support\MobileApi;
use Illuminate\Http\Exceptions\HttpResponseException;

class OnboardingProvisioningProfile
{
    public function get(?string $domain = null): array
    {
        if (! config('mobile_onboarding.enabled', true)) {
            throw new HttpResponseException(MobileApi::error(
                'onboarding_unconfigured',
                'La configuración de instalación aún no está habilitada. Contacta al administrador.',
                503,
            ));
        }

        $bind = $domain !== null;
        $code = null;
        if ($bind) {
            $domain = FirmwareCloudDomain::normalize($domain);
            if ($domain === null) {
                throw new HttpResponseException(MobileApi::error('validation_failed', 'Revisa el destino de nube.', 422,
                    ['cloud_domain' => ['Indica un host DNS o IPv4 y un puerto válido.']]));
            }
            $code = (string) config('mobile_onboarding.advertisers_code', '');
            $validCode = $code !== '' && strlen($code) <= 128 && ! preg_match('/[&=\x00-\x1F\x7F]/', $code);
            if (! config('mobile_onboarding.public_enrollment_identifier') || ! $validCode) {
                throw new HttpResponseException(MobileApi::error('cloud_target_requires_enrollment',
                    'Para cambiar el destino de nube se necesita un identificador de enrolamiento público configurado por el administrador. Puedes conservar el destino actual del ventilador.',
                    422, ['cloud_domain' => ['El cambio de destino necesita un identificador de enrolamiento público válido.']]));
            }
        }

        $profile = [
            'enabled' => true,
            'version' => 1,
            'tcp_port' => 9910,
            'cloud_domain' => $domain,
            'requires_bind' => $bind,
            'advertisers_code' => $code,
        ];

        return $profile;
    }
}
