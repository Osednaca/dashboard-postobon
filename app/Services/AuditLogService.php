<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AuditLogService extends BaseService
{
    /**
     * AuditLogService constructor.
     *
     * @param AuditLogRepositoryInterface $auditLogRepository
     */
    public function __construct(AuditLogRepositoryInterface $auditLogRepository)
    {
        parent::__construct($auditLogRepository);
    }

    /**
     * Log an action.
     *
     * @param string $action
     * @param string $entityType
     * @param int|string $entityId
     * @param int|string|null $userId
     * @param array<string, mixed>|null $details
     * @return AuditLog
     */
    public function log(
        string $action,
        string $entityType,
        int|string $entityId,
        int|string|null $userId = null,
        ?array $details = null
    ): AuditLog {
        $log = $this->repository->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        /** @var AuditLog */
        return $log;
    }

    /**
     * Get logs for a specific entity.
     *
     * @param string $entityType
     * @param int|string $entityId
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    public function getLogsForEntity(string $entityType, int|string $entityId): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get logs for a user.
     *
     * @param int|string $userId
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    public function getLogsForUser(int|string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }
}
