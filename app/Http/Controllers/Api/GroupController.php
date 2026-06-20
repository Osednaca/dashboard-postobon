<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Campaign;
use App\Models\Group;
use App\Services\GroupService;
use App\Services\Z2\Z2GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * @var GroupService
     */
    protected GroupService $groupService;

    /**
     * @var Z2GroupService
     */
    protected Z2GroupService $z2GroupService;

    /**
     * GroupController constructor.
     */
    public function __construct(GroupService $groupService, Z2GroupService $z2GroupService)
    {
        $this->groupService = $groupService;
        $this->z2GroupService = $z2GroupService;
    }

    /**
     * Display a paginated listing of groups.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('groups.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Group::class);
            $this->z2GroupService->syncGroups();
            $groups = Group::paginate(request()->input('per_page', 15));

            return response()->json($groups);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar grupos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created group.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Group::class);
            $group = $this->z2GroupService->createGroup($request->validated()['name']);

            if ($group) {
                return response()->json($group, 201);
            }

            return response()->json([
                'message' => 'Error al crear el grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified group.
     */
    public function show(Group $group): JsonResponse
    {
        try {
            $this->authorize('view', $group);
            return response()->json($group);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified group.
     */
    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);
            $updated = $this->groupService->update($group->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified group.
     */
    public function destroy(Group $group): JsonResponse
    {
        try {
            $this->authorize('delete', $group);

            if ($this->z2GroupService->deleteGroup($group->id)) {
                return response()->json([
                    'message' => 'Grupo eliminado correctamente.',
                ]);
            }

            return response()->json([
                'message' => 'Error al eliminar el grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power on all devices in a group.
     */
    public function powerOnGroup(Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);

            if ($this->z2GroupService->powerOnGroup($group->id)) {
                return response()->json([
                    'message' => 'Todos los dispositivos del grupo han sido encendidos.',
                    'group' => $group->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al encender los dispositivos del grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al encender los dispositivos del grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power off all devices in a group.
     */
    public function powerOffGroup(Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);

            if ($this->z2GroupService->powerOffGroup($group->id)) {
                return response()->json([
                    'message' => 'Todos los dispositivos del grupo han sido apagados.',
                    'group' => $group->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al apagar los dispositivos del grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al apagar los dispositivos del grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change content for all devices in a group.
     */
    public function changeContent(Request $request, Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);

            $request->validate([
                'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            ]);

            $campaign = Campaign::find($request->input('campaign_id'));
            if (! $campaign) {
                return response()->json([
                    'message' => 'Campaña no encontrada.',
                ], 404);
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return response()->json([
                    'message' => 'La campaña no tiene medios asociados.',
                ], 422);
            }

            if ($this->z2GroupService->changeGroupContent($group->id, $media->file_path)) {
                return response()->json([
                    'message' => 'Contenido cambiado correctamente en todos los dispositivos del grupo.',
                    'group' => $group->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al cambiar el contenido del grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el contenido del grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Publish a campaign to all devices in a group.
     */
    public function publishCampaign(Request $request, Group $group): JsonResponse
    {
        try {
            $this->authorize('update', $group);

            $request->validate([
                'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            ]);

            $campaign = Campaign::find($request->input('campaign_id'));
            if (! $campaign) {
                return response()->json([
                    'message' => 'Campaña no encontrada.',
                ], 404);
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return response()->json([
                    'message' => 'La campaña no tiene medios asociados.',
                ], 422);
            }

            if ($this->z2GroupService->changeGroupContent($group->id, $media->file_path)) {
                return response()->json([
                    'message' => 'Campaña publicada correctamente en todos los dispositivos del grupo.',
                    'group' => $group->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al publicar la campaña en el grupo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al publicar la campaña en el grupo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
