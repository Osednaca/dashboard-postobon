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
        'tecnico' => 'Técnico (instalador)',
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function managesOperations(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    public function operatesDevices(): bool
    {
        return array_key_exists($this->role ?? '', self::ROLES);
    }

    public function assignableRoles(): array
    {
        return $this->isSuperadmin() ? self::ROLES : ($this->role === 'admin' ? ['tecnico' => self::ROLES['tecnico']] : []);
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
