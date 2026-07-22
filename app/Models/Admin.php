<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Operator account — a fully separate auth surface from consumer {@see User}
 * (own table + `admin` guard). RBAC roles/permissions are scoped to the
 * `admin` guard so operator authorisation never overlaps user authorisation.
 */
class Admin extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasFactory, HasRoles, HasUuids, Notifiable;

    /** Spatie: resolve roles/permissions against the admin guard. */
    protected string $guard_name = 'admin';

    /** Send the operator-specific reset link (points at the admin reset page). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    protected $fillable = [
        'name', 'username', 'email', 'password', 'avatar', 'is_active',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /** Any authenticated admin is, by definition, an operator. */
    public function isOperator(): bool
    {
        return true;
    }
}
