<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'superadmin' => 'Superadministrador',
        'admin' => 'Administrador',
        'campanas' => 'Campañas',
        'tecnico' => 'Técnico (instalador)',
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function managesOperations(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'campanas'], true);
    }

    public function operatesDevices(): bool
    {
        return $this->isSuperadmin();
    }

    public function viewsDevices(): bool
    {
        return in_array($this->role, ['superadmin', 'tecnico', 'campanas'], true);
    }

    public function assignsDevices(): bool
    {
        return in_array($this->role, ['superadmin', 'tecnico'], true);
    }

    public function managesEstablishments(): bool
    {
        return $this->assignsDevices();
    }

    public function managesGroups(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    public function createsGroups(): bool
    {
        return $this->managesOperations();
    }

    public function usesMedia(): bool
    {
        return $this->managesOperations();
    }

    public function assignableRoles(): array
    {
        return $this->isSuperadmin() ? self::ROLES : [];
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Sin acceso';
    }

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
