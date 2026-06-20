<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuditController extends Controller
{
    /**
     * @var AuditLogService
     */
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
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        try {
            $logs = AuditLog::paginate(15);

            return view('audit.index', compact('logs'));
        } catch (\Exception $e) {
            Log::error('Error al listar auditoría: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar la auditoría.');
        }
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog): View
    {
        try {
            return view('audits.show', compact('auditLog'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar registro de auditoría: ' . $e->getMessage());

            return redirect()->route('audits.index')
                ->with('error', 'Ocurrió un error al cargar el registro de auditoría.');
        }
    }
}
