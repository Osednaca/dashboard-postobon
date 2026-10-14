<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeviceEstablishmentRequest;
use App\Models\Device;
use App\Models\Wl35DeviceProfile;
use App\Services\AuditLogService;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeviceEstablishmentController extends Controller
{
    public function __construct(
        private readonly UnifiedFleetClient $fleetClient,
        private readonly AuditLogService $auditLog,
    ) {}

    public function assign(AssignDeviceEstablishmentRequest $request, Device $device): JsonResponse|RedirectResponse
    {
        return $this->saveAssociation($request, $device, 'Device', $device->id);
    }

    public function assignWl35(AssignDeviceEstablishmentRequest $request, string $deviceId): JsonResponse|RedirectResponse
    {
        $profile = Wl35DeviceProfile::where('device_id', $deviceId)->first();
        if ($profile === null) {
            try {
                $device = collect($this->fleetClient->getFleet()['devices'] ?? [])
                    ->first(fn (array $item): bool => ($item['type'] ?? null) === 'wl35'
                        && (string) ($item['id'] ?? '') === $deviceId);
            } catch (Throwable) {
                abort(503, 'No fue posible verificar el equipo existente. Inténtalo nuevamente.');
            }

            abort_if($device === null, 404);
            $profile = new Wl35DeviceProfile([
                'device_id' => $deviceId,
                'name' => (string) ($device['name'] ?? $deviceId),
            ]);
        }

        return $this->saveAssociation($request, $profile, 'Wl35DeviceProfile', $deviceId);
    }

    private function saveAssociation(
        AssignDeviceEstablishmentRequest $request,
        Model $device,
        string $entityType,
        int|string $entityId,
    ): JsonResponse|RedirectResponse {
        DB::transaction(function () use ($request, $device, $entityType, $entityId): void {
            $previous = $device->establishment_id;
            $device->establishment_id = (int) $request->validated('establishment_id');
            $device->save();
            $this->auditLog->log('assign_establishment', $entityType, $device->getKey(), $request->user()->id, [
                'device_identifier' => (string) $entityId,
                'previous_establishment_id' => $previous,
                'establishment_id' => $device->establishment_id,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'establishment_id' => $device->establishment_id]);
        }

        return back()->with('success', 'Equipo vinculado al establecimiento.');
    }
}
