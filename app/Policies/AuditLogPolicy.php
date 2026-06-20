<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function restore(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
