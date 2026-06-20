<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ScheduleController extends Controller
{
    /**
     * @var ScheduleService
     */
    protected ScheduleService $scheduleService;

    /**
     * ScheduleController constructor.
     */
    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Display a paginated listing of schedules.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('schedules.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Schedule::class);

            $schedules = $this->scheduleService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($schedules);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar programaciones.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created schedule.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Schedule::class);

            $schedule = $this->scheduleService->create($request->validated());

            return response()->json($schedule, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la programación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        try {
            $this->authorize('view', $schedule);

            return response()->json($schedule);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la programación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified schedule.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        try {
            $this->authorize('update', $schedule);

            $updated = $this->scheduleService->update($schedule->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la programación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        try {
            $this->authorize('delete', $schedule);

            $this->scheduleService->delete($schedule->id);

            return response()->json([
                'message' => 'Programación eliminada correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la programación.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
