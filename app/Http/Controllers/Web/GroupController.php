<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkGroupOperationRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Group;
use App\Services\GroupCreationService;
use App\Services\GroupService;
use App\Services\Z2\Z2GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GroupController extends Controller
{
    protected GroupService $groupService;

    protected Z2GroupService $z2GroupService;

    /**
     * GroupController constructor.
     */
    public function __construct(GroupService $groupService, Z2GroupService $z2GroupService, private readonly GroupCreationService $groupCreation)
    {
        $this->groupService = $groupService;
        $this->z2GroupService = $z2GroupService;
    }

    /**
     * Display a listing of groups.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Group::class);

        try {
            $groups = Group::with('businessType')->withCount(['devices', 'wl35Devices'])->paginate(15);

            return view('groups.index', compact('groups'));
        } catch (\Exception $e) {
            Log::error('Error al listar grupos: '.$e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar los grupos.');
        }
    }

    /**
     * Show the form for creating a new group.
     */
    public function create(): View
    {
        $this->authorize('create', Group::class);

        return view('groups.create', [
            'businessTypes' => BusinessType::orderBy('name')->get(),
            'groupDevices' => $this->groupCreation->devices(),
        ]);
    }

    /**
     * Store a newly created group.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        try {
            $group = $this->groupCreation->create($request->validated(), $request->user()->id);

            if ($group) {
                return redirect()->route('groups.show', $group)
                    ->with('success', 'Grupo creado exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al crear el grupo en la nube.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al crear grupo: '.$e->getMessage());

            return back()->withInput()->with('error', 'Ocurrió un error al crear el grupo. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified group.
     */
    public function show(Group $group): View|RedirectResponse
    {
        $this->authorize('view', $group);

        try {
            $group->load(['businessType', 'devices.establishmentProfile.businessType', 'wl35Devices.establishmentProfile.businessType']);

            return view('groups.show', compact('group'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar grupo: '.$e->getMessage());

            return redirect()->route('groups.index')
                ->with('error', 'Ocurrió un error al cargar el grupo.');
        }
    }

    /**
     * Show the form for editing the specified group.
     */
    public function edit(Group $group): View
    {
        $this->authorize('update', $group);

        $catalog = collect($this->groupCreation->devices());

        return view('groups.edit', [
            'group' => $group,
            'currentDevices' => $catalog->where('group_id', $group->id)->values()->all(),
            'groupDevices' => $catalog->where('group_id', '!=', $group->id)->values()->all(),
            'businessTypes' => BusinessType::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified group.
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        try {
            $this->groupCreation->update($group, $request->validated(), $request->user()->id);

            return redirect()->route('groups.index')
                ->with('success', 'Grupo actualizado exitosamente.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al actualizar grupo: '.$e->getMessage());

            return back()->withInput()->with('error', 'Ocurrió un error al actualizar el grupo. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified group.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        try {
            if ($this->z2GroupService->deleteGroup($group->id)) {
                return redirect()->route('groups.index')
                    ->with('success', 'Grupo eliminado exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al eliminar el grupo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar grupo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar el grupo. Por favor intente nuevamente.');
        }
    }

    /**
     * Power on all devices in the group.
     */
    public function powerOnGroup(Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        try {
            if ($this->z2GroupService->powerOnGroup($group->id)) {
                return back()->with('success', 'Los equipos 42 del grupo han sido encendidos.');
            }

            return back()->with('error', 'Ocurrió un error al encender los dispositivos del grupo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al encender dispositivos del grupo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al encender los dispositivos del grupo.');
        }
    }

    /**
     * Power off all devices in the group.
     */
    public function powerOffGroup(Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        try {
            if ($this->z2GroupService->powerOffGroup($group->id)) {
                return back()->with('success', 'Los equipos 42 del grupo han sido apagados.');
            }

            return back()->with('error', 'Ocurrió un error al apagar los dispositivos del grupo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al apagar dispositivos del grupo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al apagar los dispositivos del grupo.');
        }
    }

    /**
     * Change content for all devices in the group.
     */
    public function changeContent(BulkGroupOperationRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        try {
            $campaignId = $request->input('campaign_id');
            if (! $campaignId) {
                return back()->with('error', 'Se requiere el ID de la campaña.');
            }

            $campaign = Campaign::find($campaignId);
            if (! $campaign) {
                return back()->with('error', 'Campaña no encontrada.');
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return back()->with('error', 'La campaña no tiene medios asociados.');
            }

            if ($this->z2GroupService->changeGroupContent($group->id, $media->file_path)) {
                return back()->with('success', 'Contenido cambiado para los equipos 42 del grupo.');
            }

            return back()->with('error', 'Ocurrió un error al cambiar el contenido del grupo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al cambiar contenido del grupo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al cambiar el contenido del grupo.');
        }
    }

    /**
     * Publish a campaign to all devices in the group.
     */
    public function publishCampaign(BulkGroupOperationRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        try {
            $campaignId = $request->input('campaign_id');
            if (! $campaignId) {
                return back()->with('error', 'Se requiere el ID de la campaña.');
            }

            $campaign = Campaign::find($campaignId);
            if (! $campaign) {
                return back()->with('error', 'Campaña no encontrada.');
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return back()->with('error', 'La campaña no tiene medios asociados.');
            }

            if ($this->z2GroupService->changeGroupContent($group->id, $media->file_path)) {
                return back()->with('success', 'Campaña publicada en los equipos 42 del grupo.');
            }

            return back()->with('error', 'Ocurrió un error al publicar la campaña en el grupo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al publicar campaña en el grupo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al publicar la campaña en el grupo.');
        }
    }
}
