<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteSchedulesRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Device;
use App\Models\Group;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\Wl35DeviceProfile;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
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
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', Schedule::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,executed,failed,cancelled'],
            'month' => ['nullable', 'date_format:Y-m', 'regex:/^(?!0000)\d{4}-\d{2}$/'],
            'view' => ['nullable', 'in:list,calendar'],
        ]);
        $status = $filters['status'] ?? '';
        $view = $filters['view'] ?? 'list';
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'), config('app.timezone'));

        try {
            $query = Schedule::with(['device', 'group', 'campaign'])
                ->when(! auth()->user()->managesOperations(), fn ($query) => $query
                    ->where('type', '!=', 'activate_campaign')->whereNull('campaign_id'))
                ->when($status, fn ($query) => $query->where('status', $status));
            $schedules = (clone $query)
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('scheduled_at')
                ->paginate(15)->withQueryString();
            $calendarSchedules = (clone $query)
                ->where('scheduled_at', '>=', $month)
                ->where('scheduled_at', '<', $month->addMonth())
                ->orderBy('scheduled_at')->get()
                ->groupBy(fn (Schedule $schedule) => $schedule->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d'));
            $calendarStart = $month->startOfWeek();
            $calendarEnd = $month->endOfMonth()->endOfWeek();

            return view('schedules.index', compact('schedules', 'calendarSchedules', 'calendarStart', 'calendarEnd', 'month', 'status', 'view'));
        } catch (\Exception $e) {
            Log::error('Error al listar programaciones: '.$e->getMessage());

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
        $wl35Devices = Wl35DeviceProfile::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();
        $media = Media::orderBy('name')->get();

        return view('schedules.create', compact('devices', 'wl35Devices', 'groups', 'media'));
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al crear programación: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear la programación. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule): View|RedirectResponse
    {
        $this->authorize('view', $schedule);

        try {
            return view('schedules.show', compact('schedule'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar programación: '.$e->getMessage());

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
        $wl35Devices = Wl35DeviceProfile::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();
        $media = Media::orderBy('name')->get();

        return view('schedules.edit', compact('schedule', 'devices', 'wl35Devices', 'groups', 'media'));
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error al actualizar programación: '.$e->getMessage());

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
            Log::error('Error al ejecutar programación: '.$e->getMessage());

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
            Log::error('Error al eliminar programación: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar la programación. Por favor intente nuevamente.');
        }
    }

    public function bulkDestroy(BulkDeleteSchedulesRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', Schedule::class);
        $ids = $request->validated('ids');
        $schedules = Schedule::whereIn('id', $ids)->get();
        if ($schedules->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Una programación seleccionada ya no está disponible.']);
        }

        foreach ($schedules as $schedule) {
            $this->authorize('delete', $schedule);
        }

        try {
            DB::transaction(function () use ($schedules): void {
                foreach ($schedules as $schedule) {
                    if (! $this->scheduleService->delete($schedule->id)) {
                        throw new \RuntimeException('No se pudo eliminar una programación seleccionada.');
                    }
                }
            });

            return redirect()->route('schedules.index')->with('success', 'Programaciones eliminadas exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar programaciones: '.$e->getMessage());

            return back()->with('error', 'No se pudieron eliminar las programaciones seleccionadas.');
        }
    }
}
