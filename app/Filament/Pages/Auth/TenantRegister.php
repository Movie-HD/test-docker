<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Register;
use App\Models\Organizacion;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Select;
use App\Models\Sucursal;

class TenantRegister extends Register
{
    public ?string $tenantSlug = null;

    public function mount(): void
    {
        // Obtiene el slug del tenant de la URL
        $this->tenantSlug = request()->segment(2);

        // Si es el registro inicial (/dashboard/register), permitir continuar
        if ($this->tenantSlug === 'register') {
            parent::mount();
            return;
        }

        // Para rutas de tenant, validar que exista
        if (!$this->tenantSlug || !Organizacion::where('slug', $this->tenantSlug)->exists()) {
            abort(404, 'Organización no encontrada');
        }

        parent::mount();
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        // Only show branch selection for tenant registrations
                        $this->getSucursalFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getSucursalFormComponent(): Select
    {
        // Don't show for initial registration
        if ($this->tenantSlug === 'register') {
            return Select::make('sucursal_id')->hidden();
        }

        $tenant = Organizacion::where('slug', $this->tenantSlug)->first();
        $sucursalCount = Sucursal::where('organizacion_id', $tenant->id)->count();

        // If only one branch exists, return hidden field with default value
        if ($sucursalCount === 1) {
            $sucursal = Sucursal::where('organizacion_id', $tenant->id)->first();
            return Select::make('sucursal_id')
                ->default($sucursal->id)
                ->hidden();
        }

        // Show select field if multiple branches exist
        return Select::make('sucursal_id')
            ->label('Sucursal')
            ->options(
                Sucursal::where('organizacion_id', $tenant->id)
                    ->pluck('nombre', 'id')
            )
            ->required();
    }

    protected function handleRegistration(array $data): Model
    {
        $user = parent::handleRegistration($data);

        // Solo adjuntar al tenant si no es el registro inicial
        if ($this->tenantSlug !== 'register') {
            $tenant = Organizacion::where('slug', $this->tenantSlug)->first();
            $tenant->users()->attach($user);

            // Handle branch assignment
            $sucursalCount = Sucursal::where('organizacion_id', $tenant->id)->count();

            if ($sucursalCount === 1) {
                // Automatically assign the only branch
                $sucursal = Sucursal::where('organizacion_id', $tenant->id)->first();
                $user->sucursales()->attach($sucursal->id);
            } elseif (isset($data['sucursal_id'])) {
                // Assign selected branch
                $user->sucursales()->attach($data['sucursal_id']);
            }
        }

        return $user;
    }

    public function getTitle(): string
    {
        // Título personalizado según el tipo de registro
        if ($this->tenantSlug === 'register') {
            return 'Registro Inicial';
        }

        $tenant = Organizacion::where('slug', $this->tenantSlug)->first();
        return "{$tenant->name} | Registro de usuarios";
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }
}
