<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * Display a listing of locations.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Location::class);

        try {
            $locations = Location::paginate(15);

            return view('locations.index', compact('locations'));
        } catch (\Exception $e) {
            Log::error('Error al listar ubicaciones: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar las ubicaciones.');
        }
    }

    /**
     * Show the form for creating a new location.
     */
    public function create(): View
    {
        $this->authorize('create', Location::class);

        return view('locations.create');
    }

    /**
     * Store a newly created location.
     */
    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $this->authorize('create', Location::class);

        try {
            $this->locationService->create($request->validated());

            return redirect()->route('locations.index')
                ->with('success', 'Ubicación creada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear ubicación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear la ubicación. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified location.
     */
    public function show(Location $location): View
    {
        $this->authorize('view', $location);

        try {
            return view('locations.show', compact('location'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar ubicación: ' . $e->getMessage());

            return redirect()->route('locations.index')
                ->with('error', 'Ocurrió un error al cargar la ubicación.');
        }
    }

    /**
     * Show the form for editing the specified location.
     */
    public function edit(Location $location): View
    {
        $this->authorize('update', $location);

        return view('locations.edit', compact('location'));
    }

    /**
     * Update the specified location.
     */
    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        try {
            $this->locationService->update($location->id, $request->validated());

            return redirect()->route('locations.index')
                ->with('success', 'Ubicación actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar ubicación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar la ubicación. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified location.
     */
    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        try {
            $this->locationService->delete($location->id);

            return redirect()->route('locations.index')
                ->with('success', 'Ubicación eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar ubicación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la ubicación. Por favor intente nuevamente.');
        }
    }
}
