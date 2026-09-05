<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEstablishmentRequest;
use App\Http\Requests\UpdateEstablishmentRequest;
use App\Models\BusinessType;
use App\Models\Establishment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EstablishmentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Establishment::class);

        $establishments = Establishment::with('businessType')
            ->withCount(['devices', 'wl35DeviceProfiles'])
            ->orderBy('name')
            ->paginate(20);

        return view('establishments.index', compact('establishments'));
    }

    public function create(): View
    {
        $this->authorize('create', Establishment::class);

        return view('establishments.create', [
            'businessTypes' => BusinessType::orderBy('name')->get(),
        ]);
    }

    public function store(StoreEstablishmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Establishment::class);
        Establishment::create($request->validated());

        return redirect()->route('establishments.index')
            ->with('success', 'Establecimiento creado exitosamente.');
    }

    public function edit(Establishment $establishment): View
    {
        $this->authorize('update', $establishment);

        return view('establishments.edit', [
            'establishment' => $establishment,
            'businessTypes' => BusinessType::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): RedirectResponse
    {
        $this->authorize('update', $establishment);
        $data = $request->validated();

        if (blank($data['wifi_password'] ?? null)) {
            unset($data['wifi_password']);
        }

        $establishment->update($data);

        return redirect()->route('establishments.index')
            ->with('success', 'Establecimiento actualizado exitosamente.');
    }

    public function destroy(Establishment $establishment): RedirectResponse
    {
        $this->authorize('delete', $establishment);

        if ($establishment->devices()->exists() || $establishment->wl35DeviceProfiles()->exists()) {
            return back()->with('error', 'No puedes eliminar un establecimiento que todavía tiene dispositivos asociados.');
        }

        $establishment->delete();

        return back()->with('success', 'Establecimiento eliminado exitosamente.');
    }
}
