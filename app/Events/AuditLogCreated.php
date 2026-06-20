<?php

namespace App\Events;

use App\Models\AuditLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AuditLog $auditLog;

    public function __construct(AuditLog $auditLog)
    {
        $this->auditLog = $auditLog;
    }
}
