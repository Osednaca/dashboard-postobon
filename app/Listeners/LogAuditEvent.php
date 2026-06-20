<?php

namespace App\Listeners;

use App\Events\AuditLogCreated;
use Illuminate\Support\Facades\Log;

class LogAuditEvent
{
    public function handle(AuditLogCreated $event): void
    {
        $auditLog = $event->auditLog;

        Log::info("Auditoría: {$auditLog->action}", [
            'user_id' => $auditLog->user_id,
            'entity_type' => $auditLog->entity_type,
            'entity_id' => $auditLog->entity_id,
            'details' => $auditLog->details,
            'ip_address' => $auditLog->ip_address,
            'user_agent' => $auditLog->user_agent,
        ]);
    }
}
