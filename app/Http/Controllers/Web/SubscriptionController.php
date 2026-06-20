<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    /**
     * @var SubscriptionService
     */
    protected SubscriptionService $subscriptionService;

    /**
     * SubscriptionController constructor.
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of subscriptions.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Subscription::class);

        try {
            $subscriptions = Subscription::paginate(15);

            return view('subscriptions.index', compact('subscriptions'));
        } catch (\Exception $e) {
            Log::error('Error al listar suscripciones: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar las suscripciones.');
        }
    }

    /**
     * Show the form for creating a new subscription.
     */
    public function create(): View
    {
        return view('subscriptions.create');
    }

    /**
     * Store a newly created subscription.
     */
    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        try {
            $this->subscriptionService->create($request->validated());

            return redirect()->route('subscriptions.index')
                ->with('success', 'Suscripción creada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear suscripción: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear la suscripción. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): View
    {
        try {
            return view('subscriptions.show', compact('subscription'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar suscripción: ' . $e->getMessage());

            return redirect()->route('subscriptions.index')
                ->with('error', 'Ocurrió un error al cargar la suscripción.');
        }
    }

    /**
     * Show the form for editing the specified subscription.
     */
    public function edit(Subscription $subscription): View
    {
        return view('subscriptions.edit', compact('subscription'));
    }

    /**
     * Update the specified subscription.
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        try {
            $this->subscriptionService->update($subscription->id, $request->validated());

            return redirect()->route('subscriptions.index')
                ->with('success', 'Suscripción actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar suscripción: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar la suscripción. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): RedirectResponse
    {
        try {
            $this->subscriptionService->delete($subscription->id);

            return redirect()->route('subscriptions.index')
                ->with('success', 'Suscripción eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar suscripción: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la suscripción. Por favor intente nuevamente.');
        }
    }
}
