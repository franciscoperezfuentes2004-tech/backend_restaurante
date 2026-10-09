<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at', 'branch_id', 'branch_name', 'sucursal_id', 'using_default_credentials', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, Auditable, BelongsToSucursal;

    // Helpers para verificar rol fácilmente
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        $roleAliases = [
            'repartidor'  => ['repartidor', 'driver', 'delivery'],
            'driver'      => ['repartidor', 'driver', 'delivery'],
            'mesero'      => ['mesero', 'waiter'],
            'waiter'      => ['mesero', 'waiter'],
            'cocina'      => ['cocina', 'kitchen', 'cook'],
            'kitchen'     => ['cocina', 'kitchen', 'cook'],
            'admin'       => ['admin', 'administrador'],
            'super_admin' => ['super_admin', 'superadmin'],
            'gerente'     => ['gerente', 'manager'],
        ];

        $userRoles = $roleAliases[$this->role] ?? [$this->role];

        foreach ($roles as $r) {
            $expanded = $roleAliases[$r] ?? [$r];
            if (count(array_intersect($userRoles, $expanded)) > 0) {
                return true;
            }
        }

        return in_array($this->role, $roles);
    }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isGerente(): bool    { return $this->role === 'gerente'; }
    public function isMesero(): bool     { return $this->role === 'mesero'; }
    public function isCocina(): bool     { return $this->role === 'cocina'; }
    public function isRepartidor(): bool { return $this->role === 'repartidor'; }

    public function getTelefonoAttribute(): ?string
    {
        return $this->phone;
    }

    public function setTelefonoAttribute($value): void
    {
        $this->attributes['phone'] = $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'using_default_credentials' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
            'sucursal_id' => 'integer',
        ];
    }

    public function getBranchIdAttribute(): ?int
    {
        return $this->attributes['branch_id'] ?? $this->attributes['sucursal_id'] ?? null;
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function testimonials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function userSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function unreadNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class, 'user_id')->whereNull('read_at');
    }
}
