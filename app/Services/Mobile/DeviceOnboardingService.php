<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Models\DeviceOnboarding;
use App\Models\Establishment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\MobileApi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceOnboardingService
{
    public function __construct(
        private readonly OnboardingTelemetry $telemetry,
        private readonly OnboardingProvisioningProfile $profile,
        private readonly AuditLogService $audit,
    ) {}

    public function prepare(User $actor, array $data, string $key): DeviceOnboarding
    {
        $data['cloud_domain'] = $data['cloud_domain'] ?? null;
        $fingerprint = [
            $data['device_type'], $data['expected_identifier'], $data['name'],
            (int) $data['establishment_id'], (int) $data['play_mode'], (int) $data['sync_class'],
        ];
        // Preserve historical fingerprints when omitted/null means keep network.cfg.
        if ($data['cloud_domain'] !== null) {
            $fingerprint[] = $data['cloud_domain'];
        }
        $hash = hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
        $existing = DeviceOnboarding::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            $this->sameRequest($existing, $hash);

            return $this->status($existing, $actor);
        }

        $profile = $this->profile->get($data['cloud_domain']);
        $snapshot = $this->telemetry->find($data['expected_identifier'], true);
        try {
            return DB::transaction(function () use ($actor, $data, $key, $hash, $snapshot, $profile): DeviceOnboarding {
                // Serializes same-actor idempotency while unique indexes protect different actors.
                User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $existing = DeviceOnboarding::where('user_id', $actor->id)->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    $this->sameRequest($existing, $hash);

                    return $existing;
                }
                Establishment::whereKey($data['establishment_id'])->lockForUpdate()->firstOrFail();
                $this->rejectAssigned($data['expected_identifier']);
                DeviceOnboarding::where('active_identifier', $data['expected_identifier'])
                    ->where('expires_at', '<=', now())
                    ->update(['active_identifier' => null, 'status' => 'expired']);
                if (DeviceOnboarding::where('active_identifier', $data['expected_identifier'])->exists()) {
                    $this->fail('onboarding_in_progress', 'Este ventilador ya tiene una instalación en curso.');
                }
                $session = DeviceOnboarding::create([
                    ...$data,
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $actor->id,
                    'active_identifier' => $data['expected_identifier'],
                    'status' => 'prepared',
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                    'provisioning_profile' => $profile,
                    'baseline_heartbeat_count' => $this->count($snapshot),
                    'baseline_last_seen_at' => $this->telemetry->seenAt($snapshot),
                    'expires_at' => now()->addMinutes(max(1, (int) config('mobile_onboarding.session_minutes', 30))),
                ]);
                $this->audit->log('mobile_onboarding_prepared', 'DeviceOnboarding', $session->id, $actor->id, [
                    'session_uuid' => $session->uuid,
                    'expected_identifier' => $session->expected_identifier,
                    'establishment_id' => $session->establishment_id,
                    'play_mode' => $session->play_mode,
                    'sync_class' => $session->sync_class,
                    'cloud_domain' => $session->cloud_domain,
                ]);

                return $session;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $existing = DeviceOnboarding::where('user_id', $actor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                $this->sameRequest($existing, $hash);

                return $existing;
            }
            $this->fail('onboarding_in_progress', 'Este ventilador ya tiene una instalación en curso.');
        }
    }

    public function update(DeviceOnboarding $session, User $actor, array $data): DeviceOnboarding
    {
        $this->authorize($session, $actor);

        return DB::transaction(function () use ($session, $actor, $data): DeviceOnboarding {
            $session = DeviceOnboarding::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->active($session);
            if (! in_array($session->status, ['prepared', 'local_connected'], true)) {
                $this->fail('onboarding_locked', 'Vuelve a preparar una instalación para cambiar datos después del envío.');
            }
            if ((int) ($data['play_mode'] ?? $session->play_mode) === 0 && (int) ($data['sync_class'] ?? $session->sync_class) !== 0) {
                $this->fail('invalid_sync_class', 'El modo individual utiliza la clase A (0).', 422);
            }
            if (isset($data['establishment_id'])) {
                Establishment::whereKey($data['establishment_id'])->lockForUpdate()->firstOrFail();
            }
            $session->fill($data)->save();
            $this->audit->log('mobile_onboarding_updated', 'DeviceOnboarding', $session->id, $actor->id, [
                'session_uuid' => $session->uuid,
                'fields' => array_keys($data),
            ]);

            return $session;
        }, 3);
    }

    public function event(DeviceOnboarding $session, User $actor, array $data): DeviceOnboarding
    {
        $this->authorize($session, $actor);
        // A replay must not reset the baseline and require endlessly newer heartbeats.
        if ($data['event'] === 'configuration_sent' && $session->configuration_reported_at) {
            return $this->status($session, $actor);
        }
        $snapshot = $data['event'] === 'configuration_sent'
            ? $this->telemetry->find($session->expected_identifier, true) : null;

        return DB::transaction(function () use ($session, $actor, $data, $snapshot): DeviceOnboarding {
            $session = DeviceOnboarding::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->active($session);
            if ($data['event'] === 'configuration_sent' && $session->configuration_reported_at) {
                return $session;
            }
            if ($data['event'] === 'local_connected') {
                if (! in_array($session->status, ['prepared', 'local_connected'], true)) {
                    $this->fail('invalid_transition', 'La instalación ya pasó a la verificación de conexión.');
                }
                $session->status = 'local_connected';
            } else {
                if (! in_array($session->status, ['prepared', 'local_connected'], true)) {
                    $this->fail('invalid_transition', 'No se puede registrar el envío en el estado actual.');
                }
                $session->forceFill([
                    'status' => 'awaiting_online',
                    'configuration_reported_at' => now(),
                    'verification_baseline_at' => now(),
                    'baseline_heartbeat_count' => max($session->baseline_heartbeat_count, $this->count($snapshot)),
                    'baseline_last_seen_at' => $this->telemetry->seenAt($snapshot),
                ]);
            }
            $session->last_result_code = $data['result_code'] ?? null;
            $session->save();
            $this->audit->log('mobile_onboarding_event', 'DeviceOnboarding', $session->id, $actor->id, [
                'session_uuid' => $session->uuid,
                'event' => $data['event'],
                'result_code' => $session->last_result_code,
            ]);

            return $session;
        }, 3);
    }

    public function status(DeviceOnboarding $session, User $actor): DeviceOnboarding
    {
        $this->authorize($session, $actor);
        $snapshot = $session->establishment_id !== null && in_array($session->status, ['awaiting_online', 'ready_to_complete'], true) && ! $session->expires_at->isPast()
            ? $this->telemetry->find($session->expected_identifier) : null;

        return DB::transaction(function () use ($session, $snapshot): DeviceOnboarding {
            $session = DeviceOnboarding::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($this->invalidateMissingEstablishment($session)) {
                return $session;
            }
            if (! in_array($session->status, ['completed', 'cancelled', 'expired', 'failed'], true) && $session->expires_at->isPast()) {
                $session->forceFill(['status' => 'expired', 'active_identifier' => null])->save();
            }
            if (in_array($session->status, ['awaiting_online', 'ready_to_complete'], true)) {
                $session->status = $this->verified($session, $snapshot) ? 'ready_to_complete' : 'awaiting_online';
                $session->save();
            }

            return $session;
        }, 3);
    }

    public function complete(DeviceOnboarding $session, User $actor): DeviceOnboarding
    {
        $this->authorize($session, $actor);
        if ($session->status === 'completed') {
            return $session;
        }
        if ($session->establishment_id === null) {
            // Persist the terminal state before rejecting completion; throwing inside a
            // transaction would otherwise roll back the reservation release.
            $session = $this->status($session, $actor);
        }
        $this->active($session);
        $snapshot = $this->telemetry->find($session->expected_identifier, true);

        $result = DB::transaction(function () use ($session, $actor, $snapshot): DeviceOnboarding {
            $session = DeviceOnboarding::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status === 'completed') {
                return $session;
            }
            if ($this->invalidateMissingEstablishment($session)) {
                return $session;
            }
            $this->active($session);
            if (! $this->verified($session, $snapshot)) {
                $this->fail('device_not_verified', 'Aún no hay una señal nueva que confirme la conexión y la clase de sincronización.');
            }
            $establishment = Establishment::whereKey($session->establishment_id)->lockForUpdate()->first();
            if (! $establishment) {
                $this->fail('establishment_unavailable', 'El establecimiento ya no está disponible.');
            }
            $device = $this->rejectAssigned($session->expected_identifier);
            if ($device === null) {
                // Existing private-cloud sync can discover this same identity concurrently.
                $device = Device::withTrashed()->firstOrCreate(['mac_address' => $session->expected_identifier], [
                    'name' => $session->name,
                    'status' => 'online',
                ]);
                $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
                if ($device->establishment_id !== null || $device->trashed()) {
                    $this->fail('device_already_assigned', 'El ventilador ya está asignado o no está disponible para registrar.');
                }
            }
            $device->forceFill([
                'name' => $session->name,
                'establishment_id' => $establishment->id,
                'status' => 'online',
                'last_heartbeat_at' => $this->telemetry->seenAt($snapshot),
            ])->save();
            $session->forceFill([
                'device_id' => $device->id,
                'status' => 'completed',
                'active_identifier' => null,
                'completed_at' => now(),
            ])->save();
            $this->audit->log('mobile_onboarding_completed', 'Device', $device->id, $actor->id, [
                'session_uuid' => $session->uuid,
                'establishment_id' => $establishment->id,
                'play_mode' => $session->play_mode,
                'sync_class' => $session->sync_class,
                'verification' => 'private_cloud_fresh_heartbeat_and_reported_mode',
            ]);

            return $session;
        }, 3);

        if ($result->status === 'failed') {
            $this->active($result);
        }

        return $result;
    }

    public function cancel(DeviceOnboarding $session, User $actor): DeviceOnboarding
    {
        $this->authorize($session, $actor);

        return DB::transaction(function () use ($session, $actor): DeviceOnboarding {
            $session = DeviceOnboarding::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (in_array($session->status, ['cancelled', 'failed'], true)) {
                return $session;
            }
            if ($this->invalidateMissingEstablishment($session)) {
                return $session;
            }
            // Recovery must be able to discard an expired attempt without reopening it.
            // A completed installation remains immutable even after its old expiry time.
            if ($session->status !== 'completed' && ($session->status === 'expired' || $session->expires_at->isPast())) {
                $session->forceFill(['status' => 'expired', 'active_identifier' => null])->save();

                return $session;
            }
            $this->active($session);
            $session->forceFill(['status' => 'cancelled', 'active_identifier' => null])->save();
            $this->audit->log('mobile_onboarding_cancelled', 'DeviceOnboarding', $session->id, $actor->id, [
                'session_uuid' => $session->uuid,
                'configuration_already_sent' => $session->configuration_reported_at !== null,
            ]);

            return $session;
        }, 3);
    }

    private function verified(DeviceOnboarding $session, ?array $snapshot): bool
    {
        if (! $snapshot || ! $session->configuration_reported_at || ! $session->verification_baseline_at
            || ($snapshot['online'] ?? false) !== true || $this->count($snapshot) <= $session->baseline_heartbeat_count) {
            return false;
        }
        $seen = $this->telemetry->seenAt($snapshot);
        if (! $seen || $seen->lessThanOrEqualTo($session->verification_baseline_at)
            || $seen->lessThan(now()->subSeconds(max(1, (int) config('mobile_onboarding.heartbeat_freshness_seconds', 90))))
            || $seen->greaterThan(now()->addSeconds(5))) {
            return false;
        }

        return isset($snapshot['playMode'], $snapshot['deviceClass'])
            && in_array($snapshot['playMode'], [0, 1, '0', '1'], true)
            && (is_int($snapshot['deviceClass']) || (is_string($snapshot['deviceClass']) && preg_match('/\A(?:[0-9]|1[0-4])\z/', $snapshot['deviceClass'])))
            && (int) $snapshot['playMode'] === $session->play_mode
            && (int) $snapshot['deviceClass'] === $session->sync_class;
    }

    private function rejectAssigned(string $identifier): ?Device
    {
        // Legacy inventory can store colon-delimited MACs, so compare normalized identities.
        $device = Device::withTrashed()
            ->whereRaw("UPPER(REPLACE(REPLACE(mac_address, ':', ''), '-', '')) = ?", [$identifier])
            ->lockForUpdate()->first();
        if ($device && ($device->establishment_id !== null || $device->trashed())) {
            $this->fail('device_already_assigned', 'El ventilador ya está asignado o no está disponible para registrar.');
        }

        return $device;
    }

    private function authorize(DeviceOnboarding $session, User $actor): void
    {
        if (! $actor->assignsDevices() || ($session->user_id !== $actor->id && ! $actor->isSuperadmin())) {
            $this->fail('not_found', 'No se encontró la instalación solicitada.', 404);
        }
    }

    private function active(DeviceOnboarding $session): void
    {
        if ($session->expires_at->isPast() || in_array($session->status, ['completed', 'cancelled', 'expired', 'failed'], true)) {
            $this->fail('onboarding_inactive', 'Esta instalación finalizó o expiró. Prepara una nueva si es necesario.');
        }
    }

    private function invalidateMissingEstablishment(DeviceOnboarding $session): bool
    {
        if ($session->establishment_id === null && ! in_array($session->status, ['completed', 'cancelled', 'expired', 'failed'], true)) {
            $session->forceFill([
                'status' => 'failed',
                'last_result_code' => 'establishment_unavailable',
                'active_identifier' => null,
            ])->save();

            return true;
        }

        return $session->status === 'failed';
    }

    private function sameRequest(DeviceOnboarding $session, string $hash): void
    {
        if (! hash_equals($session->request_hash, $hash)) {
            $this->fail('idempotency_conflict', 'La clave de operación ya se utilizó con otros datos.');
        }
    }

    private function count(?array $snapshot): int
    {
        $value = $snapshot['heartbeatCount'] ?? 0;

        return is_int($value) && $value >= 0 ? $value : 0;
    }

    private function fail(string $code, string $message, int $status = 409): never
    {
        throw new HttpResponseException(MobileApi::error($code, $message, $status));
    }
}
