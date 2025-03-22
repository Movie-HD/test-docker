<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use App\Models\Sucursal;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();
        $sucursalCount = Sucursal::where('organizacion_id', $tenant->id)->count();

        if ($sucursalCount === 1) {
            $sucursal = Sucursal::where('organizacion_id', $tenant->id)->first();
            $this->record->sucursales()->attach($sucursal->id);
        }
    }
}
