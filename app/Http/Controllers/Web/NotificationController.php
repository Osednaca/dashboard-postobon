<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * Display a listing of notifications.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Notification::class);

        try {
            $notifications = Notification::paginate(15);

            return view('notifications.index', compact('notifications'));
        } catch (\Exception $e) {
            Log::error('Error al listar notificaciones: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar las notificaciones.');
        }
    }

    /**
     * Display the specified notification.
     */
    public function show(Notification $notification): View
    {
        try {
            return view('notifications.show', compact('notification'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar notificación: ' . $e->getMessage());

            return redirect()->route('notifications.index')
                ->with('error', 'Ocurrió un error al cargar la notificación.');
        }
    }

    /**
     * Mark the specified notification as read.
     */
    public function markAsRead(Notification $notification): RedirectResponse
    {
        try {
            $notification->update(['read_at' => now()]);

            return back()->with('success', 'Notificación marcada como leída.');
        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al marcar la notificación como leída.');
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): RedirectResponse
    {
        try {
            $userId = auth()->id();

            if ($userId !== null) {
                $this->notificationService->markAsRead($userId);
            }

            return back()->with('success', 'Todas las notificaciones han sido marcadas como leídas.');
        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones como leídas: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al marcar todas las notificaciones como leídas.');
        }
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Notification $notification): RedirectResponse
    {
        try {
            $this->notificationService->delete($notification->id);

            return back()->with('success', 'Notificación eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la notificación.');
        }
    }
}
