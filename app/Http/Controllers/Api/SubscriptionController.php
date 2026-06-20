<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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
     * Display a paginated listing of subscriptions.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('subscriptions.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Subscription::class);

            $subscriptions = $this->subscriptionService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($subscriptions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar suscripciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created subscription.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Subscription::class);

            $subscription = $this->subscriptionService->create($request->validated());

            return response()->json($subscription, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la suscripción.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): JsonResponse
    {
        try {
            $this->authorize('view', $subscription);

            return response()->json($subscription);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la suscripción.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified subscription.
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        try {
            $this->authorize('update', $subscription);

            $updated = $this->subscriptionService->update($subscription->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la suscripción.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified subscription.
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        try {
            $this->authorize('delete', $subscription);

            $this->subscriptionService->delete($subscription->id);

            return response()->json([
                'message' => 'Suscripción eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la suscripción.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
