<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileListRequest;
use App\Http\Requests\Mobile\UpdateMobileEstablishmentRequest;
use App\Http\Requests\StoreEstablishmentRequest;
use App\Http\Resources\Mobile\EstablishmentResource;
use App\Models\Establishment;
use App\Services\AuditLogService;
use App\Support\MobileApi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EstablishmentController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(MobileListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Establishment::class);
        $data = $request->validated();
        $query = Establishment::with('businessType')->withCount(['devices', 'wl35DeviceProfiles']);
        if (filled($data['search'] ?? null)) {
            $search = $data['search'];
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('address', 'like', '%'.$search.'%'));
        }

        return EstablishmentResource::collection($query->orderBy('name')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function show(Establishment $establishment): EstablishmentResource
    {
        $this->authorize('view', $establishment);

        return $this->resource($establishment);
    }

    public function store(StoreEstablishmentRequest $request): EstablishmentResource
    {
        $this->authorize('create', Establishment::class);
        $establishment = DB::transaction(function () use ($request): Establishment {
            $establishment = Establishment::create($request->validated());
            $this->audit->log('mobile.establishment.created', Establishment::class, $establishment->id, $request->user()->id);

            return $establishment;
        });

        return $this->resource($establishment);
    }

    public function update(UpdateMobileEstablishmentRequest $request, Establishment $establishment): EstablishmentResource
    {
        $this->authorize('update', $establishment);
        DB::transaction(function () use ($request, $establishment): void {
            $locked = Establishment::whereKey($establishment->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            if (blank($data['wifi_password'] ?? null)) {
                unset($data['wifi_password']);
            }
            $locked->update($data);
            $this->audit->log('mobile.establishment.updated', Establishment::class, $locked->id, $request->user()->id);
        });

        return $this->resource($establishment->refresh());
    }

    public function destroy(Request $request, Establishment $establishment): Response
    {
        $this->authorize('delete', $establishment);

        return DB::transaction(function () use ($request, $establishment): Response {
            $locked = Establishment::whereKey($establishment->id)->lockForUpdate()->firstOrFail();
            if ($locked->devices()->exists() || $locked->wl35DeviceProfiles()->exists()) {
                return MobileApi::error('establishment_has_devices', 'El establecimiento todavía tiene dispositivos asociados.', 409);
            }
            $locked->delete();
            $this->audit->log('mobile.establishment.deleted', Establishment::class, $locked->id, $request->user()->id);

            return response()->noContent();
        });
    }

    private function resource(Establishment $establishment): EstablishmentResource
    {
        return new EstablishmentResource($establishment->load('businessType')->loadCount(['devices', 'wl35DeviceProfiles']));
    }
}
