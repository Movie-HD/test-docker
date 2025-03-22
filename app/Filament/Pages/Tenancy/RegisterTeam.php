<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Organizacion;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RegisterTeam extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Registrar Organizacion';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name'),
                TextInput::make('slug'),
            ]);
    }

    protected function handleRegistration(array $data): Organizacion
    {
        $team = Organizacion::create($data);
        $user = auth()->user(); // Obtener usuario autenticado
        $team->users()->attach($user);

        // ✅ Crear el rol si no existe y asociarlo a un team_id
        $role = Role::create([
            'team_id' => $team->id,
            'name' => 'super-admin',
            'guard_name' => 'web',
            'organizacion_id' => $team->id,
        ]);

        // Sincronizar permisos al rol (opcional)
        $role->syncPermissions(Permission::all());

        // ✅ Asegurar que 'team_id' no sea nulo
        $user->roles()->attach($role->id, ['team_id' => $team->id]);

        // Crear sucursal principal
        $sucursal = $team->sucursals()->create([
            'nombre' => 'Principal',
            'direccion' => null,
            'telefono' => null,
        ]);

        // Asignar sucursal al usuario
        $user->sucursales()->attach($sucursal->id);

        return $team;
    }
}
