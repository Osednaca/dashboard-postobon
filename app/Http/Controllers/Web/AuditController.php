<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditExportRequest;
use App\Http\Requests\AuditIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TabularExportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuditController extends Controller
{
    protected AuditLogService $auditLogService;

    /**
     * AuditController constructor.
     */
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of audit logs.
     */
    public function index(AuditIndexRequest $request): View|RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->safe()->except('page');

        try {
            $logs = $this->filteredLogs($filters)->paginate(15)->appends($filters);
            $users = User::whereHas('auditLogs')->orderBy('name')->orderBy('id')->get(['id', 'name']);
            $actions = AuditLog::distinct()->orderBy('action')->pluck('action');
            $entityTypes = AuditLog::distinct()->orderBy('entity_type')->pluck('entity_type');

            return view('audit.index', compact('logs', 'filters', 'users', 'actions', 'entityTypes'));
        } catch (\Exception $e) {
            Log::error('Error al listar auditoría: '.$e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar la auditoría.');
        }
    }

    public function export(AuditExportRequest $request, TabularExportService $exporter): Response|RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);
        $filters = $request->safe()->except(['format', 'page']);

        try {
            $logs = $this->filteredLogs($filters)->get();
            $rows = $logs->map(fn (AuditLog $log): array => [
                $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha registrada',
                $log->user?->name ?? 'Sin usuario disponible',
                $log->action, $log->entity_type, $log->entity_id,
                $log->details === null ? 'Sin detalles registrados' : json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                $log->ip_address ?? 'Sin IP registrada',
            ])->all();
            $notes = ['Todos los resultados de los filtros aplicados. Zona horaria: '.config('app.timezone').'. Total: '.$logs->count().'.'];
            foreach (['user_id' => 'ID de usuario', 'action' => 'Acción', 'entity_type' => 'Entidad', 'date' => 'Fecha'] as $field => $label) {
                if (filled($filters[$field] ?? null)) {
                    $notes[] = $label.': '.$filters[$field];
                }
            }

            return $exporter->download($request->validated('format'), 'auditoria', 'Auditoría', [[
                'name' => 'Historial de auditoría',
                'headers' => ['Fecha', 'Usuario', 'Acción registrada', 'Entidad registrada', 'ID', 'Detalles', 'IP'],
                'rows' => $rows,
            ]], $notes);
        } catch (\Throwable $exception) {
            Log::error('Error al exportar la auditoría.', ['exception' => $exception]);

            return redirect()->route('audit.index', $filters)
                ->with('error', 'No se pudo generar la exportación de auditoría. Inténtalo de nuevo.');
        }
    }

    private function filteredLogs(array $filters): Builder
    {
        $query = AuditLog::with('user:id,name');
        foreach (['user_id', 'action', 'entity_type'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['date'] ?? null)) {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], config('app.timezone'));
            $query->where('created_at', '>=', $day)->where('created_at', '<', $day->addDay());
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog): View|RedirectResponse
    {
        try {
            return view('audits.show', compact('auditLog'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar registro de auditoría: '.$e->getMessage());

            return redirect()->route('audits.index')
                ->with('error', 'Ocurrió un error al cargar el registro de auditoría.');
        }
    }
}
