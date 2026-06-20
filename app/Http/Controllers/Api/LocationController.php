<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * @var LocationService
     */
    protected LocationService $locationService;

    /**
     * LocationController constructor.
     */
    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Display a paginated listing of locations.
     */
    public function index(): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('locations.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Location::class);

            $locations = $this->locationService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($locations);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar ubicaciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created location.
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Location::class);

            $location = $this->locationService->create($request->validated());

            return response()->json($location, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la ubicación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location): JsonResponse
    {
        try {
            $this->authorize('view', $location);

            return response()->json($location);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la ubicación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified location.
     */
    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        try {
            $this->authorize('update', $location);

            $updated = $this->locationService->update($location->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la ubicación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Location $location): JsonResponse
    {
        try {
            $this->authorize('delete', $location);

            $this->locationService->delete($location->id);

            return response()->json([
                'message' => 'Ubicación eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la ubicación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
