<div class="fi-fo-field-wrp-label">
    <div class="flex justify-end mt-4 mb-4">
        <button 
            type="button"
            wire:click="sendTestEmail"
            class="fi-btn fi-btn-color-primary fi-btn-size-md inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors shadow-sm"
        >
            <svg wire:loading wire:target="sendTestEmail" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg wire:loading.remove wire:target="sendTestEmail" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            <span wire:loading.remove wire:target="sendTestEmail">Enviar Email de Prueba</span>
            <span wire:loading wire:target="sendTestEmail">Enviando...</span>
        </button>
    </div>
</div>

