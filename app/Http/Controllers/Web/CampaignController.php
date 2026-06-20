<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMediaRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * Display a listing of campaigns.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Campaign::class);

        try {
            $campaigns = $this->campaignService->paginate(15);

            return view('campaigns.index', compact('campaigns'));
        } catch (\Exception $e) {
            Log::error('Error al listar campañas: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar las campañas.');
        }
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.create');
    }

    /**
     * Store a newly created campaign.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);

        try {
            $campaign = $this->campaignService->create($request->validated());

            if ($request->filled('videos')) {
                $videoIds = explode(',', $request->input('videos'));
                foreach ($videoIds as $index => $videoId) {
                    if (!empty($videoId)) {
                        $this->campaignService->attachMedia($campaign->id, (int) $videoId, $index + 1);
                    }
                }
            }

            return redirect()->route('campaigns.index')
                ->with('success', 'Campaña creada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear la campaña. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        try {
            return view('campaigns.show', compact('campaign'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar campaña: ' . $e->getMessage());

            return redirect()->route('campaigns.index')
                ->with('error', 'Ocurrió un error al cargar la campaña.');
        }
    }

    /**
     * Show the form for editing the specified campaign.
     */
    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('campaigns.edit', compact('campaign'));
    }

    /**
     * Update the specified campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $data = $request->validated();
            if ($request->has('cities')) {
                $data['segment_cities'] = $request->input('cities') ?? [];
            }
            if ($request->has('groups')) {
                $data['segment_groups'] = $request->input('groups') ?? [];
            }

            $this->campaignService->update($campaign->id, $data);

            if ($request->has('videos')) {
                $campaign->media()->detach();
                $videoIds = $request->filled('videos') ? explode(',', $request->input('videos')) : [];
                foreach ($videoIds as $index => $videoId) {
                    if (!empty($videoId)) {
                        $this->campaignService->attachMedia($campaign->id, (int) $videoId, $index + 1);
                    }
                }
            }

            return redirect()->route('campaigns.index')
                ->with('success', 'Campaña actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar la campaña. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        try {
            $this->campaignService->delete($campaign->id);

            return redirect()->route('campaigns.index')
                ->with('success', 'Campaña eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la campaña. Por favor intente nuevamente.');
        }
    }

    /**
     * Activate the campaign.
     */
    public function activate(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $this->z2CampaignSyncService->activate($campaign);

            return back()->with('success', 'Campaña activada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al activar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al activar la campaña.');
        }
    }

    /**
     * Pause the campaign.
     */
    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $this->z2CampaignSyncService->pause($campaign);

            return back()->with('success', 'Campaña pausada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al pausar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al pausar la campaña.');
        }
    }

    /**
     * Finish the campaign.
     */
    public function finish(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $this->z2CampaignSyncService->finish($campaign);

            return back()->with('success', 'Campaña finalizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al finalizar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al finalizar la campaña.');
        }
    }

    /**
     * Schedule the campaign.
     */
    public function schedule(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $campaign->update(['status' => 'scheduled']);
            Log::info('Campaña programada', ['campaign_id' => $campaign->id]);

            return back()->with('success', 'Campaña programada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al programar campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al programar la campaña.');
        }
    }

    /**
     * Add media to the campaign.
     */
    public function addMedia(AssignMediaRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $mediaIds = $request->validated()['media_ids'] ?? [];
            foreach ($mediaIds as $mediaId) {
                $campaign->media()->attach($mediaId);
            }

            if ($campaign->status === 'active') {
                $this->z2CampaignSyncService->publishToDevices(
                    $campaign,
                    $campaign->deviceCampaigns()->pluck('device_id')->toArray()
                );
            }

            return back()->with('success', 'Medio agregado a la campaña exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al agregar medio a campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al agregar el medio a la campaña.');
        }
    }

    /**
     * Remove media from the campaign.
     */
    public function removeMedia(Campaign $campaign, int|string $mediaId): RedirectResponse
    {
        $this->authorize('update', $campaign);

        try {
            $campaign->media()->detach($mediaId);

            if ($campaign->status === 'active') {
                $this->z2CampaignSyncService->publishToDevices(
                    $campaign,
                    $campaign->deviceCampaigns()->pluck('device_id')->toArray()
                );
            }

            return back()->with('success', 'Medio removido de la campaña exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al remover medio de campaña: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al remover el medio de la campaña.');
        }
    }
}
