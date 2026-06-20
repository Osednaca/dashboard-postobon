<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    /**
     * @var NotificationService
     */
    protected NotificationService $notificationService;

    /**
     * NotificationController constructor.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a paginated listing of notifications.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('notifications.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Notification::class);

            $notifications = $this->notificationService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar notificaciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created notification.
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Notification::class);

            $notification = $this->notificationService->create($request->validated());

            return response()->json($notification, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la notificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified notification.
     */
    public function show(Notification $notification): JsonResponse
    {
        try {
            $this->authorize('view', $notification);

            return response()->json($notification);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la notificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified notification.
     */
    public function update(UpdateNotificationRequest $request, Notification $notification): JsonResponse
    {
        try {
            $this->authorize('update', $notification);

            $updated = $this->notificationService->update($notification->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la notificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        try {
            $this->authorize('delete', $notification);

            $this->notificationService->delete($notification->id);

            return response()->json([
                'message' => 'Notificación eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la notificación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        try {
            $this->authorize('update', $notification);

            $updated = $this->notificationService->update($notification->id, [
                'read_at' => now(),
            ]);

            return response()->json([
                'message' => 'Notificación marcada como leída.',
                'notification' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al marcar la notificación como leída.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $userId = auth()->id();

            if ($userId === null) {
                return response()->json([
                    'message' => 'Usuario no autenticado.',
                ], 401);
            }

            $this->notificationService->markAsRead($userId);

            return response()->json([
                'message' => 'Todas las notificaciones han sido marcadas como leídas.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al marcar las notificaciones como leídas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
