<?php

namespace App\Http\Resources\Mobile;

use App\Services\Mobile\OnboardingProvisioningProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceOnboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = null;
        if (in_array($this->status, ['prepared', 'local_connected'], true)) {
            $profile = app(OnboardingProvisioningProfile::class)->get();
        }

        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'device_type' => $this->device_type,
            'expected_identifier' => $this->expected_identifier,
            'name' => $this->name,
            'establishment_id' => $this->establishment_id,
            'play_mode' => $this->play_mode,
            'sync_class' => $this->sync_class,
            'last_result_code' => $this->last_result_code,
            'expires_at' => $this->expires_at->toIso8601String(),
            'configuration_reported_at' => $this->configuration_reported_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'device_id' => $this->device_id ? 'z2:'.$this->device_id : null,
            'verification' => $this->status === 'completed' ? 'private_cloud_fresh_heartbeat_and_reported_mode' : null,
            'provisioning_profile' => $profile,
        ];
    }
}
