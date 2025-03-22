@if(in_array($getRecord()->status, ['pending', 'disconnected', 'qr_expired']))
    @if($getRecord() && $getRecord()->qr_code)
        <x-filament::card>
            <div class="text-center">
                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Escanea el código QR con WhatsApp') }}
                    </x-slot>

                    <x-slot name="description">
                        <div class="space-y-2">
                            <p>
                                {{ __('El código QR expirará en aproximadamente 45 segundos') }}
                            </p>
                        </div>
                    </x-slot>

                    <div class="flex justify-center p-4">
                        <img src="{{ $getRecord()->qr_code }}"
                            alt="WhatsApp QR Code"
                            class="w-64 h-64">
                    </div>

                </x-filament::section>
            </div>
        </x-filament::card>
    @endif
@endif
