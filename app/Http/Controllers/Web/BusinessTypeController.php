<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessTypeRequest;
use App\Http\Requests\UpdateBusinessTypeRequest;
use App\Models\BusinessType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', BusinessType::class);

        $businessTypes = BusinessType::withCount('establishments')->orderBy('name')->paginate(20);

        return view('business-types.index', compact('businessTypes'));
    }

    public function store(StoreBusinessTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', BusinessType::class);
        BusinessType::create($request->validated());

        return back()->with('success', 'Tipo de negocio creado exitosamente.');
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $this->authorize('update', $businessType);
        $businessType->update($request->validated());

        return back()->with('success', 'Tipo de negocio actualizado exitosamente.');
    }

    public function destroy(BusinessType $businessType): RedirectResponse
    {
        $this->authorize('delete', $businessType);

        if ($businessType->establishments()->exists()) {
            return back()->with('error', 'No puedes eliminar un tipo de negocio que todavía tiene establecimientos asociados.');
        }

        $businessType->delete();

        return back()->with('success', 'Tipo de negocio eliminado exitosamente.');
    }
}
