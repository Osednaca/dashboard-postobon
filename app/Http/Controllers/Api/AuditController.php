<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

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
     * Display a paginated listing of audit logs.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('audit.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', AuditLog::class);

            $logs = $this->auditLogService->paginate(
                request()->input('per_page', 15)
            );

            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar registros de auditoría.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified audit log.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        try {
            $this->authorize('view', $auditLog);

            return response()->json($auditLog);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el registro de auditoría.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
