<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsappInstanceResource\Pages;
use App\Filament\Resources\WhatsappInstanceResource\RelationManagers;
use App\Models\Sucursal;
use App\Models\WhatsappInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput; # Agregar si es un Input [Form]
use Filament\Tables\Columns\TextColumn; # Agregar si es un Column [Table]
use Filament\Facades\Filament; # Se agrega para obtener solo los roles del tenant actual
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsappInstanceResource extends Resource
{
    protected static ?string $model = WhatsappInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('phone_number')
                    ->label('Número de WhatsApp')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->prefix('+')
                    ->tel()
                    ->numeric()
                    ->rules(['regex:/^[0-9]{10,15}$/'])
                    ->helperText('Ingresa el número sin espacios ni caracteres especiales'),

                Forms\Components\Select::make('sucursales')
                    ->relationship('sucursales', 'nombre', modifyQueryUsing: fn (Builder $query) => $query->whereBelongsTo(Filament::getTenant()) )
                    ->multiple()
                    ->required()
                    ->label('Sucursales')
                    ->searchable()
                    ->preload()
                    ->helperText('Selecciona las sucursales que usarán este número')
                    ->visible(function () {
                        $tenant = Filament::getTenant();
                        return Sucursal::where('organizacion_id', $tenant->id)->count() > 1;
                    }),

                Forms\Components\View::make('filament.components.qr-code')
                    ->visible(fn ($record) => $record && $record->qr_code)
                    ->label('Código QR'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone_number')
                    ->label('Número de WhatsApp')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'pending' => 'warning',
                        'disconnected' => 'danger',
                        'qr_expired' => 'danger',
                    })
                    ->formatStateUsing(function ($state, WhatsappInstance $record) {
                        $connectionState = static::getConnectionState($record);

                        if ($connectionState && isset($connectionState['instance']['state'])) {
                            $currentState = $connectionState['instance']['state'];

                            $newStatus = match ($currentState) {
                                'open' => 'connected',
                                'connecting' => 'pending',
                                'close', 'disconnected' => 'disconnected',
                                default => $state
                            };

                            if ($newStatus !== $state) {
                                $record->update(['status' => $newStatus]);
                                return $newStatus;
                            }
                        }
                        return $state;
                    }),

                TextColumn::make('sucursales.nombre')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
                    ->label('Probar Conexión')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (WhatsappInstance $record) {
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
                    ->visible(fn ($record) => $record->status === 'connected'),

                Tables\Actions\DeleteAction::make()
                    ->before(function (WhatsappInstance $record) {
                        static::deleteInstance($record);
                    }),
            ])
            ->poll('20s'); // Actualizar la tabla cada 10 segundos
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsappInstances::route('/'),
            'create' => Pages\CreateWhatsappInstance::route('/create'),
            'edit' => Pages\EditWhatsappInstance::route('/{record}/edit'),
        ];
    }

    public static function getConnectionState(WhatsappInstance $instance): ?array
    {
         try {
             $baseUrl = config('services.evolution.base_url');
             $apiKey = config('services.evolution.api_key');
             $instanceName = $instance->instance_name;

             $response = Http::withHeaders([
                 'apikey' => $apiKey,
             ])->get($baseUrl . '/instance/connectionState/' . $instanceName);

             Log::info('Respuesta de estado de conexión', [
                 'status' => $response->status(),
                 'body' => $response->json(),
                 'instance' => $instanceName
             ]);

             if ($response->successful()) {
                 return $response->json();
             }

         } catch (\Exception $e) {
             Log::error('Error al obtener estado de conexión', [
                 'error' => $e->getMessage(),
                 'instance' => $instance->id
             ]);
         }
         return null;
     }

    public static function generateQR(WhatsappInstance $instance): void
    {
        try {
            $baseUrl = config('services.evolution.base_url');
            $apiKey = config('services.evolution.api_key');
            $instanceName = "instance_{$instance->id}";

            // Intentar eliminar la instancia existente primero
            Http::withHeaders([
                'apikey' => $apiKey,
            ])->delete($baseUrl . '/instance/delete/' . $instanceName);

            // Crear nueva instancia
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post($baseUrl . '/instance/create', [
                'instanceName' => $instanceName,
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS'
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['qrcode']['base64'])) {
                    $instance->update([
                        'qr_code' => $responseData['qrcode']['base64'],
                        'instance_name' => $instanceName,
                        'status' => 'pending',
                        'qr_generated_at' => now() // Agregar este campo a la migración
                    ]);

                    Notification::make()
                        ->success()
                        ->title('QR generado correctamente')
                        ->body('Tienes 45 segundos para escanear el código QR')
                        ->send();
                }
            } else {
                throw new \Exception('Error en la respuesta: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Error al generar QR', [
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->danger()
                ->title('Error al generar el QR')
                ->body('Error al procesar la solicitud. Intente nuevamente.')
                ->send();
        }
    }

    public static function resetInstance(WhatsappInstance $instance): void
    {
        try {
            $baseUrl = config('services.evolution.base_url');
            $apiKey = config('services.evolution.api_key');

            // Obtener el estado actual
            $connectionState = static::getConnectionState($instance);
            $currentState = $connectionState['instance']['state'] ?? null;

            Log::info('Estado actual antes de reiniciar', [
                'state' => $currentState,
                'instance' => $instance->instance_name
            ]);

            // Si está en estado 'close', necesitamos eliminar y crear una nueva instancia
            if ($currentState === 'close') {
                static::deleteInstance($instance);
                static::generateQR($instance);
                return;
            }

            // Si está en estado 'connecting', intentamos reiniciar
            if ($currentState === 'connecting') {
                $response = Http::withHeaders([
                    'apikey' => $apiKey,
                ])->post($baseUrl . '/instance/restart/' . $instance->instance_name);

                Log::info('Respuesta del reinicio de instancia', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'instance_name' => $instance->instance_name
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Error al reiniciar la instancia');
                }

                // Si el reinicio fue exitoso, generamos un nuevo QR
                static::generateQR($instance);
            }

            Notification::make()
                ->success()
                ->title('Instancia reiniciada')
                ->body('Se ha generado un nuevo código QR')
                ->send();

        } catch (\Exception $e) {
            Log::error('Error al reiniciar instancia', [
                'error' => $e->getMessage(),
                'instance' => $instance->id,
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->danger()
                ->title('Error al reiniciar')
                ->body('No se pudo reiniciar la instancia. Intente nuevamente.')
                ->send();
        }
    }


    public static function deleteInstance(WhatsappInstance $instance): void
    {
        if ($instance->instance_name) {
            try {
                $response = Http::withHeaders([
                    'apikey' => config('services.evolution.api_key'),
                ])->delete(config('services.evolution.base_url') . "/instance/delete/{$instance->instance_name}");

                Log::info('Respuesta de eliminación de instancia WhatsApp', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'instance_id' => $instance->id
                ]);

            } catch (\Exception $e) {
                Log::error('Error al eliminar instancia de WhatsApp', [
                    'error' => $e->getMessage(),
                    'instance_id' => $instance->id
                ]);
            }
        }
    }

}
