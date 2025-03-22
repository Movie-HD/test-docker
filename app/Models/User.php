<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            ? in_array($this->email, [
                'test@example.com',
                // agrega más emails autorizados
              ])
            : true; // Allow all users to access the panel, modify this according to your needs
    }

    # Metodos de multi-tenancy
    public function organizacion(): BelongsToMany
    {
        return $this->belongsToMany(Organizacion::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->organizacion;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->organizacion()->whereKey($tenant)->exists();
    }

    # Metodo de relacion con sucursales
    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class);
    }
}
