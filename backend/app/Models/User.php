<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Authorizable;

    protected $fillable = [
        'username',
        'password',
        'role',
        'status',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function canAccessFilament(): bool
    {
        return $this->isActive() && ($this->isAdmin() || $this->isFinance());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && ($this->isAdmin() || $this->isFinance());
    }

    public function getFilamentName(): string
    {
        return (string) $this->username;
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['username'] ?? $this->username ?? '');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isFinance()
    {
        return $this->role === 'finance';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function balanceAdjustments()
    {
        return $this->hasMany(BalanceAdjustment::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function canManageChannels()
    {
        return $this->isAdmin();
    }

    public function canManageUsers()
    {
        return $this->isAdmin();
    }

    public function canManageSettings()
    {
        return $this->isAdmin();
    }

    public function canExportData()
    {
        return $this->isAdmin() || $this->isFinance();
    }

    public function canAdjustBalances()
    {
        return $this->isAdmin();
    }
}
