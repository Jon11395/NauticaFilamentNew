<div x-data="{ open: false }">
    <button 
        @click="open = !open" 
        type="button"
        class="w-full rounded-lg bg-primary-50 border border-primary-200 p-4 hover:bg-primary-100 transition-colors mb-4"
    >
        <h3 class="text-lg font-semibold text-primary-900 flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Guía de Configuración Rápida de Gmail OAuth
            </span>
            <svg 
                class="w-5 h-5 transition-transform" 
                :class="{ 'rotate-180': open }"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </h3>
    </button>
    
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
    >
        <div class="rounded-lg bg-white border border-gray-200 p-6 space-y-4">
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Paso 1: Crear Proyecto y Habilitar API de Gmail</h4>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2">
                    <li>Ir a <a href="https://console.cloud.google.com/" target="_blank" class="text-primary-600 hover:underline">Google Cloud Console</a></li>
                    <li>Crear/Seleccionar proyecto</li>
                    <li>APIs y Servicios > Biblioteca > Habilitar "Gmail API"</li>
                </ol>
            </div>

            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Paso 2: Pantalla de Consentimiento OAuth</h4>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2">
                    <li>APIs y Servicios > Pantalla de consentimiento OAuth</li>
                    <li>Elegir "Externo" > Llenar nombre de app y correo de soporte</li>
                    <li>Guardar en todos los pasos</li>
                    <li>Agregar Usuarios de Prueba: agregar tu dirección de Gmail > Guardar</li>
                </ol>
            </div>

            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Paso 3: Crear Credenciales OAuth 2.0</h4>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2">
                    <li>APIs y Servicios > Credenciales > Crear Client ID de OAuth</li>
                    <li>Seleccionar "Aplicación Web"</li>
                    <li>Agregar URI de redirección: <code class="bg-gray-100 px-1 rounded text-xs">https://developers.google.com/oauthplayground</code></li>
                    <li>Guardar Client ID y Client Secret</li>
                </ol>
            </div>

            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Paso 4: Obtener Refresh Token</h4>
                <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2">
                    <li>Ir a <a href="https://developers.google.com/oauthplayground/" target="_blank" class="text-primary-600 hover:underline">OAuth Playground</a></li>
                    <li>Icono de engranaje ⚙️ > Marcar "Usar tus propias credenciales OAuth"</li>
                    <li>Ingresar Client ID y Secret del Paso 3</li>
                    <li>Seleccionar "Gmail API v1" > Marcar <code class="bg-gray-100 px-1 rounded text-xs">https://mail.google.com/</code></li>
                    <li>Autorizar APIs (iniciar sesión con usuario de prueba de Gmail)</li>
                    <li>Intercambiar código por tokens > Copiar "Refresh token"</li>
                </ol>
            </div>

            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Paso 5: Configurar Abajo y Probar</h4>
                <p class="text-sm text-gray-700 ml-2">Llenar las credenciales abajo y hacer clic en el botón "Probar Conexión de Gmail"</p>
            </div>
        </div>
    </div>
</div>

