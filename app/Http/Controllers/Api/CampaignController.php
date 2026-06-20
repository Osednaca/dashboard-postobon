<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * @var CampaignService
     */
    protected CampaignService $campaignService;

    /**
     * @var Z2CampaignSyncService
     */
    protected Z2CampaignSyncService $z2CampaignSyncService;

    /**
     * CampaignController constructor.
     */
    public function __construct(CampaignService $campaignService, Z2CampaignSyncService $z2CampaignSyncService)
    {
        $this->campaignService = $campaignService;
        $this->z2CampaignSyncService = $z2CampaignSyncService;
    }

    /**
     * Display a paginated listing of campaigns.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('campaigns.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Campaign::class);
            $campaigns = $this->campaignService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($campaigns);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar campañas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created campaign.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Campaign::class);
            $campaign = $this->campaignService->create($request->validated());

            return response()->json($campaign, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('view', $campaign);
            return response()->json($campaign);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);
            $updated = $this->campaignService->update($campaign->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('delete', $campaign);
            $this->campaignService->delete($campaign->id);

            return response()->json([
                'message' => 'Campaña eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate a campaign.
     */
    public function activate(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);
            $this->z2CampaignSyncService->activate($campaign);

            return response()->json([
                'message' => 'Campaña activada correctamente.',
                'campaign' => $campaign->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al activar la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pause a campaign.
     */
    public function pause(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);
            $this->z2CampaignSyncService->pause($campaign);

            return response()->json([
                'message' => 'Campaña pausada correctamente.',
                'campaign' => $campaign->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al pausar la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finish a campaign.
     */
    public function finish(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);
            $this->z2CampaignSyncService->finish($campaign);

            return response()->json([
                'message' => 'Campaña finalizada correctamente.',
                'campaign' => $campaign->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al finalizar la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add media to a campaign.
     */
    public function addMedia(Request $request, Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);

            $request->validate([
                'media_id' => ['required', 'integer', 'exists:media,id'],
                'order' => ['nullable', 'integer', 'min:0'],
            ]);

            $this->campaignService->attachMedia(
                $campaign->id,
                $request->input('media_id'),
                $request->input('order')
            );

            if ($campaign->status === 'active') {
                $this->z2CampaignSyncService->publishToDevices(
                    $campaign,
                    $campaign->deviceCampaigns()->pluck('device_id')->toArray()
                );
            }

            return response()->json([
                'message' => 'Medio agregado correctamente a la campaña.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar medio a la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove media from a campaign.
     */
    public function removeMedia(Request $request, Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);

            $request->validate([
                'media_id' => ['required', 'integer', 'exists:media,id'],
            ]);

            $this->campaignService->detachMedia(
                $campaign->id,
                $request->input('media_id')
            );

            if ($campaign->status === 'active') {
                $this->z2CampaignSyncService->publishToDevices(
                    $campaign,
                    $campaign->deviceCampaigns()->pluck('device_id')->toArray()
                );
            }

            return response()->json([
                'message' => 'Medio removido correctamente de la campaña.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al remover medio de la campaña.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
