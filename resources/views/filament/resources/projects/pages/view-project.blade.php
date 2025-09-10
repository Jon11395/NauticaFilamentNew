<x-filament-panels::page>
 
    <x-filament::section icon="heroicon-o-rectangle-stack" icon-size="md">
        <x-slot name="heading">
            Proyecto: <span class="text-gray-900">{{ $record->name }}</span>
        </x-slot>

        <!-- Subheading Image -->
        @if ($record->image)
            <div class="-mx-6 mb-4" style="height:30rem; margin-top: -24.5px;">
                <img src="{{ asset('storage/' . $record->image) }}" alt="Imagen del proyecto" 
                    class="w-full h-full mb-4 object-cover object-center " >
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Project Details -->
            <div class="space-y-4">
                <div class="space-y-1">
                    <div class="flex items-start">
                        <strong class="w-32 text-gray-700">Fecha inicio:</strong>
                        <span class="text-gray-900">{{ Carbon\Carbon::parse($record->start_date)->format('d M, Y') }}</span>
                    </div>
                    <span class="block text-gray-600 text-sm">
                        {{ Carbon\Carbon::parse($record->start_date)->diffForHumans() }}
                    </span>
                </div>
            </div>
            
            
            
            <!-- Status and Offer Details -->
            <div class="space-y-4">
                <div class="flex items-center">
                    <x-filament::badge 
                        icon="{{
                            $record->status == 'in_progress' ? 'heroicon-s-arrow-right-circle' :
                            ($record->status == 'stopped' ? 'heroicon-c-x-circle' :
                            ($record->status == 'finished' ? 'heroicon-s-check-circle' : 'default_icon'))}}" 
                        color="{{
                            $record->status == 'in_progress' ? 'warning' :
                            ($record->status == 'stopped' ? 'danger' :
                            ($record->status == 'finished' ? 'success' : 'default_color'))}}">
                        <strong class="mr-2 text-gray-700">Estado:</strong>
                        <span class="text-gray-900">{{
                            $record->status == 'in_progress' ? 'En proceso' :
                            ($record->status == 'stopped' ? 'Detenido' :
                            ($record->status == 'finished' ? 'Terminado' : 'default_label'))}}
                        </span>
                    </x-filament::badge>
                </div>
                <div class="flex items-center">
                    <strong class="w-32 text-gray-700">Monto oferta:</strong>
                    <span class="text-gray-900">{{ number_format($record->offer_amount, 2) }} CRC</span>
                </div>
            </div>
        </div>
        
    </x-filament::section>
       

</x-filament-panels::page>