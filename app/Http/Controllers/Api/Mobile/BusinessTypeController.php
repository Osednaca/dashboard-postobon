<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileListRequest;
use App\Http\Requests\StoreBusinessTypeRequest;
use App\Http\Requests\UpdateBusinessTypeRequest;
use App\Http\Resources\Mobile\BusinessTypeResource;
use App\Models\BusinessType;
use App\Services\AuditLogService;
use App\Support\MobileApi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BusinessTypeController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(MobileListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BusinessType::class);
        $data = $request->validated();

        return BusinessTypeResource::collection(BusinessType::withCount('establishments')
            ->when(filled($data['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$data['search'].'%'))
            ->orderBy('name')->paginate($data['per_page'] ?? 20)->withQueryString());
    }

    public function store(StoreBusinessTypeRequest $request): BusinessTypeResource
    {
        $this->authorize('create', BusinessType::class);
        $type = DB::transaction(function () use ($request): BusinessType {
            $type = BusinessType::create($request->validated());
            $this->audit->log('mobile.business_type.created', BusinessType::class, $type->id, $request->user()->id);

            return $type;
        });

        return new BusinessTypeResource($type->loadCount('establishments'));
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): BusinessTypeResource
    {
        $this->authorize('update', $businessType);
        DB::transaction(function () use ($request, $businessType): void {
            $businessType->update($request->validated());
            $this->audit->log('mobile.business_type.updated', BusinessType::class, $businessType->id, $request->user()->id);
        });

        return new BusinessTypeResource($businessType->loadCount('establishments'));
    }

    public function destroy(Request $request, BusinessType $businessType): Response
    {
        $this->authorize('delete', $businessType);

        return DB::transaction(function () use ($request, $businessType): Response {
            $locked = BusinessType::whereKey($businessType->id)->lockForUpdate()->firstOrFail();
            if ($locked->establishments()->exists()) {
                return MobileApi::error('business_type_has_establishments', 'El tipo de negocio tiene establecimientos asociados.', 409);
            }
            $locked->delete();
            $this->audit->log('mobile.business_type.deleted', BusinessType::class, $locked->id, $request->user()->id);

            return response()->noContent();
        });
    }
}
