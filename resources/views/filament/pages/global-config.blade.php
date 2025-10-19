<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="space-y-6">
            {{ $this->form }}
            
            <div class="flex justify-start">
                @foreach ($this->getFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
