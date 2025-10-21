<div class="w-full bg-white">
    @if(empty($employees) || $employees->isEmpty())
        <div class="text-center py-12">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Sin datos de planilla</h3>
            <p class="text-gray-500">
                @if(empty($dateFrom) || empty($dateTo))
                    Seleccione las fechas en el paso anterior para ver el resumen de empleados.
                @else
                    No se encontraron empleados con horas trabajadas en el período seleccionado.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Resumen de Planilla</h3>
                        <p class="text-sm text-gray-600">
                            Período: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}
                        </p>
                    </div>
                    <button wire:click="loadData" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    Actualizar
                </button>
                </div>
            </div>
            
            <!-- Employee Cards -->
            <div class="grid gap-4">
            @foreach($employees as $employee)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                        <!-- Employee Header -->
                        <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-lg font-semibold text-gray-900">{{ $employee['name'] }}</h4>
                         
                            </div>
                        </div>

                        <!-- Employee Details -->
                        <div class="p-6">
                            @if(isset($employee['hours']) && $employee['hours'] > 0)
                                <!-- Hourly Payroll View -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Hours & Rates Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Horas y Tarifas</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Horas regulares:</span>
                                                <span class="font-medium text-gray-900">{{ number_format($employee['hours'], 1) }}h</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Horas extra:</span>
                                                <span class="font-medium text-gray-900">{{ number_format($employee['extra_hours'], 1) }}h</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Noches trabajadas:</span>
                                                <span class="font-medium text-gray-900">{{ $employee['night_days'] }} días</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Tarifa por hora:</span>
                                                <span class="font-medium text-gray-900">₡{{ number_format($employee['hourly_rate'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Salary Breakdown Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Desglose de Salario</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Salario base:</span>
                                                <span class="font-medium text-gray-900">₡{{ number_format($employee['salario_base'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Adicionales:</span>
                                                <span class="font-medium text-blue-600">+₡{{ number_format($employee['adicionales'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Rebajas:</span>
                                                <span class="font-medium text-red-600">-₡{{ number_format($employee['rebajos'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">CCSS:</span>
                                                <span class="font-medium text-orange-600">-₡{{ number_format($employee['ccss'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Resumen</h5>
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-green-800">Total Final:</span>
                                                <span class="text-xl font-bold text-green-700">₡{{ number_format($employee['salario_total'], 2) }}</span>
                                            </div>
                                            <div class="text-xs text-green-600 mt-1">
                                                Salario base + Adicionales - Rebajas - CCSS
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <!-- Fixed Payroll View -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Hours & Rates Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Horas y Tarifas</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Horas regulares:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Horas extra:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Noches trabajadas:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Tarifa por hora:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Salary Breakdown Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Desglose de Salario</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Salario base:</span>
                                                <span class="font-medium text-gray-900">₡{{ number_format($employee['salario_base'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Adicionales:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">Rebajas:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600">CCSS:</span>
                                                <span class="font-medium text-gray-400">N/A</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-2">Resumen</h5>
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-green-800">Monto a pagar:</span>
                                                <span class="text-xl font-bold text-green-700">₡{{ number_format($employee['salario_total'], 2) }}</span>
                                            </div>
                                            <div class="text-xs text-green-600 mt-1">
                                                Salario fijo establecido
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Grand Totals Banner -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 py-12 px-6 shadow-lg">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-col lg:flex-row items-center justify-between gap-8">
                        <!-- Left side - Title and period -->
                        <div class="text-center lg:text-left">
                            <h3 class="text-3xl font-bold text-gray-900 mb-2">Total a Pagar</h3>
                            <p class="text-gray-700 text-lg">
                                {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}
                            </p>
                            <p class="text-gray-600 text-sm mt-1">Monto total de la planilla</p>
                        </div>
                        
                        <!-- Right side - Amount -->
                        <div class="text-center lg:text-right">
                            <div class="text-7xl font-bold text-gray-900 mb-2">₡{{ number_format($totals['total_salario_total'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>