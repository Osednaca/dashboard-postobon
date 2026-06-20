<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Group;
use App\Models\Media;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * Display a listing of schedules.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Schedule::class);

        try {
            $schedules = Schedule::with(['device', 'group', 'campaign'])->paginate(15);

            return view('schedules.index', compact('schedules'));
        } catch (\Exception $e) {
            Log::error('Error al listar programaciones: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar las programaciones.');
        }
    }

    /**
     * Show the form for creating a new schedule.
     */
    public function create(): View
    {
        $this->authorize('create', Schedule::class);

        $devices = Device::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();
        $campaigns = Campaign::where('status', 'active')->orderBy('name')->get();
        $media = Media::orderBy('name')->get();

        return view('schedules.create', compact('devices', 'groups', 'campaigns', 'media'));
    }

    /**
     * Store a newly created schedule.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create', Schedule::class);

        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'pending';

            $this->scheduleService->create($data);

            return redirect()->route('schedules.index')
                ->with('success', 'Programación creada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear programación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear la programación. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule): View
    {
        $this->authorize('view', $schedule);

        try {
            return view('schedules.show', compact('schedule'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar programación: ' . $e->getMessage());

            return redirect()->route('schedules.index')
                ->with('error', 'Ocurrió un error al cargar la programación.');
        }
    }

    /**
     * Show the form for editing the specified schedule.
     */
    public function edit(Schedule $schedule): View
    {
        $this->authorize('update', $schedule);

        $devices = Device::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();
        $campaigns = Campaign::where('status', 'active')->orderBy('name')->get();
        $media = Media::orderBy('name')->get();

        return view('schedules.edit', compact('schedule', 'devices', 'groups', 'campaigns', 'media'));
    }

    /**
     * Update the specified schedule.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        try {
            $this->scheduleService->update($schedule->id, $request->validated());

            return redirect()->route('schedules.index')
                ->with('success', 'Programación actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar programación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar la programación. Por favor intente nuevamente.');
        }
    }

    /**
     * Execute the schedule immediately.
     */
    public function execute(Schedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        try {
            $this->scheduleService->execute($schedule->id);

            return back()->with('success', 'Programación ejecutada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al ejecutar programación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al ejecutar la programación.');
        }
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->authorize('delete', $schedule);

        try {
            $this->scheduleService->delete($schedule->id);

            return redirect()->route('schedules.index')
                ->with('success', 'Programación eliminada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar programación: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la programación. Por favor intente nuevamente.');
        }
    }
}
