<?php

namespace App\Filament\Admin\Resources\OrganizacionResource\Pages;

use App\Filament\Admin\Resources\OrganizacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizacions extends ListRecords
{
    protected static string $resource = OrganizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
