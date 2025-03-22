<?php

namespace App\Filament\Resources\WhatsappInstanceResource\Pages;

use App\Filament\Resources\WhatsappInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EditWhatsappInstance extends EditRecord
{
    protected static string $resource = WhatsappInstanceResource::class;

    // Añadimos una propiedad para contar las verificaciones
    public int $checkCount = 0;

    // Añadimos una propiedad para almacenar el estado actual
    public string $currentState = '';

    // Añadimos una propiedad para controlar el intervalo de verificación
    public int $pollInterval = 5;

    public function mount($record): void
    {
        parent::mount($record);

        // Obtenemos el registro correctamente
        $record = $this->getRecord();

        // Verificamos que el registro existe y tiene la propiedad status
        if ($record && isset($record->status)) {
            // Establecer el intervalo inicial según el estado actual
            $this->updatePollInterval($record->status);

            // Establecer el estado actual
            $this->currentState = $record->status;
        } else {
            // Valores predeterminados si no hay estado
            $this->pollInterval = 5;
            $this->currentState = 'unknown';
        }
    }

    // Método para actualizar el intervalo de verificación según el estado
    private function updatePollInterval(string $status): void
    {
        $this->pollInterval = $status === 'connected' ? 15 : 5;
    }

    public function checkConnectionStatus(): void
    {
        // Incrementamos el contador cada vez que se ejecuta la verificación
        $this->checkCount++;

        $record = $this->getRecord();
        $connectionState = WhatsappInstanceResource::getConnectionState($record);

        // Registramos en el log para verificar que se está ejecutando
        Log::info('Verificación de estado ejecutada', [
            'count' => $this->checkCount,
            'time' => now()->toDateTimeString(),
            'instance_id' => $record->id,
            'interval' => $this->pollInterval
        ]);

        if ($connectionState && isset($connectionState['instance']['state'])) {
            $currentState = $connectionState['instance']['state'];

            $newStatus = match ($currentState) {
                'open' => 'connected',
                'connecting' => 'pending',
                'close', 'disconnected' => 'disconnected',
                default => $record->status
            };

            // Actualizar el estado actual para mostrarlo en el botón
            $this->currentState = $newStatus;

            if ($newStatus !== $record->status) {
                $record->update(['status' => $newStatus]);

                // Actualizar el intervalo de verificación según el nuevo estado
                $this->updatePollInterval($newStatus);

                Notification::make()
                    ->success()
                    ->title('Estado actualizado')
                    ->body('El estado de la conexión ha cambiado a: ' . $newStatus)
                    ->send();

                // Recargar la página para actualizar los botones visibles
                $this->redirect(request()->header('Referer'));
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('connection_status')
                ->label(function () {
                    $statusLabels = [
                        'connected' => '🟢 Conectado',
                        'pending' => '🟡 Conectando...',
                        'disconnected' => '🔴 Desconectado',
                        'qr_expired' => '⚠️ QR Expirado',
                        'unknown' => '❓ Estado desconocido',
                    ];

                    return $statusLabels[$this->currentState] ?? 'Estado: ' . $this->currentState;
                })
                ->icon('heroicon-o-signal')
                ->extraAttributes([
                    'wire:poll.' . $this->pollInterval . 's' => 'checkConnectionStatus',
                    'class' => 'cursor-default'
                ])
                ->color(function () {
                    $statusColors = [
                        'connected' => 'success',
                        'pending' => 'warning',
                        'disconnected' => 'danger',
                        'qr_expired' => 'danger',
                        'unknown' => 'secondary',
                    ];

                    return $statusColors[$this->currentState] ?? 'secondary';
                })
                ->disabled(),

            Actions\Action::make('test_connection')
                ->label('Probar Conexión')
                ->icon('heroicon-o-paper-airplane')
                ->action(function () {
                    $record = $this->getRecord();
                    try {
                        $response = Http::withHeaders([
                            'apikey' => config('services.evolution.api_key'),
                            'Content-Type' => 'application/json'
                        ])->post(config('services.evolution.base_url') . '/message/sendText/' . $record->instance_name, [
                            'number' => $record->phone_number,
                            'text' => '🟢 Conexión exitosa!'
                        ]);

                        Log::info('Prueba de conexión', [
                            'response' => $response->json()
                        ]);

                        if ($response->successful()) {
                            Notification::make()
                                ->success()
                                ->title('Mensaje de prueba enviado')
                                ->send();
                        } else {
                            throw new \Exception('Error al enviar mensaje: ' . $response->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en prueba de conexión', [
                            'error' => $e->getMessage()
                        ]);

                        Notification::make()
                            ->danger()
                            ->title('Error al probar conexión')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn () => $this->getRecord()->status === 'connected'),

            Actions\Action::make('reset_instance')
                ->label('Reiniciar conexión')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    WhatsappInstanceResource::resetInstance($this->getRecord());
                })
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'disconnected', 'qr_expired']))
                ->color('primary'),

            Actions\DeleteAction::make()
                ->before(function ($record) {
                    WhatsappInstanceResource::deleteInstance($record);
                }),
        ];
    }
}
