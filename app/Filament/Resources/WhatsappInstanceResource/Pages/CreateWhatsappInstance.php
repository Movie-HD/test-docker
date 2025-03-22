<?php

namespace App\Filament\Resources\WhatsappInstanceResource\Pages;

use App\Filament\Resources\WhatsappInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use App\Models\Sucursal;
use App\Models\WhatsappTemplate;

class CreateWhatsappInstance extends CreateRecord
{
    protected static string $resource = WhatsappInstanceResource::class;

    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();
        $sucursalCount = Sucursal::where('organizacion_id', $tenant->id)->count();

        if ($sucursalCount === 1) {
            $sucursal = Sucursal::where('organizacion_id', $tenant->id)->first();
            $this->record->sucursales()->attach($sucursal->id);
        }

        // Generar QR automáticamente después de crear la instancia
        static::$resource::generateQR($this->record);

        // Crear plantillas por defecto
        $this->createDefaultTemplates();
    }

    protected function createDefaultTemplates(): void
    {
        $welcomeTemplate = [
            'whatsapp_instance_id' => $this->record->id,
            'nombre' => 'Mensaje de Bienvenida',
            'mensaje' => "¡Hola {nombre_cliente}! 👋\n\n".
                        "Bienvenido a {nombre_negocio}. Gracias por tu primera orden #{numero_orden}.\n\n".
                        "Detalles de tu orden:\n".
                        "Fecha: {fecha_orden}\n".
                        "Total: {total_orden}\n\n".
                        "¡Esperamos verte pronto nuevamente!",
            'is_default' => true,
        ];

        $orderTemplate = [
            'whatsapp_instance_id' => $this->record->id,
            'nombre' => 'Nueva Orden',
            'mensaje' => "¡Hola {nombre_cliente}! 🛍️\n\n".
                        "Tu orden #{numero_orden} ha sido registrada exitosamente.\n\n".
                        "Detalles:\n".
                        "Fecha: {fecha_orden}\n".
                        "Total: {total_orden}\n\n".
                        "¡Gracias por tu preferencia!",
            'is_default' => true,
        ];

        WhatsappTemplate::create($welcomeTemplate);
        WhatsappTemplate::create($orderTemplate);
    }

}
