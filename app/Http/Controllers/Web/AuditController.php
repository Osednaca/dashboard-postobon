<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
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
            $logs = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->appends($filters);
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
