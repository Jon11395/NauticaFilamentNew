<div class="w-full bg-white dark:bg-gray-800">
    @if(empty($employees) || $employees->isEmpty())
        <div class="text-center py-12">
            <div class="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Sin datos de planilla</h3>
            <p class="text-gray-500 dark:text-gray-400">
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
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <h3 class="text-xl font-bold !text-black dark:!text-white mb-2">Resumen de Planilla</h3>
                <p class="text-sm text-gray-600 dark:text-white">
                    Período: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}
                </p>
            </div>
            
            <!-- Employee Cards -->
            <div class="grid gap-4">
            @foreach($employees as $employee)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                        <!-- Employee Header -->
                        <div class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 px-6 py-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-lg font-semibold !text-black dark:!text-white">{{ $employee['name'] }}</h4>
                         
                            </div>
                        </div>

                        <!-- Employee Details -->
                        <div class="p-6">
                            @php
                                // Determine if this is hourly payroll
                                // If payrollType is set, use it; otherwise check if employee has hours data
                                if (isset($payrollType)) {
                                    $isHourlyPayroll = $payrollType === 'hourly';
                                } else {
                                    // Fallback: check if employee has hours data
                                    $isHourlyPayroll = isset($employee['hours']) && $employee['hours'] > 0;
                                }
                            @endphp
                            @if($isHourlyPayroll)
                                <!-- Hourly Payroll View -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Hours & Rates Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Horas y Tarifas</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Horas regulares:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($employee['hours'], 1) }}h</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Horas extra:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($employee['extra_hours'], 1) }}h</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Noches trabajadas:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $employee['night_days'] }} días</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Tarifa por hora:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">₡{{ number_format($employee['hourly_rate'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Salary Breakdown Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Desglose de Salario</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Salario base:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">₡{{ number_format($employee['salario_base'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Adicionales:</span>
                                                <span class="font-medium text-blue-600 dark:text-blue-400">+₡{{ number_format($employee['adicionales'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Rebajas:</span>
                                                <span class="font-medium text-red-600 dark:text-red-400">-₡{{ number_format($employee['rebajos'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">CCSS:</span>
                                                <span class="font-medium text-orange-600 dark:text-orange-400">-₡{{ number_format($employee['ccss'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Resumen</h5>
                                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-green-800 dark:text-green-300">Total Final:</span>
                                                <span class="text-xl font-bold text-green-700 dark:text-green-400">₡{{ number_format($employee['salario_total'], 2) }}</span>
                                            </div>
                                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
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
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Información</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Tipo:</span>
                                                <span class="font-medium text-gray-400 dark:text-gray-500">Salario Fijo</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Empleado:</span>
                                                <span class="font-medium text-gray-400 dark:text-gray-500">{{ $employee['name'] }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Salary Breakdown Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Desglose de Salario</h5>
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Salario base:</span>
                                                <span class="font-medium text-gray-900 dark:text-gray-100">₡{{ number_format($employee['salario_base'] ?? 0, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Adicionales:</span>
                                                <span class="font-medium {{ ($employee['adicionales'] ?? 0) > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500' }}">+₡{{ number_format($employee['adicionales'] ?? 0, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">Rebajas:</span>
                                                <span class="font-medium {{ ($employee['rebajos'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}">-₡{{ number_format($employee['rebajos'] ?? 0, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">CCSS:</span>
                                                <span class="font-medium {{ ($employee['ccss'] ?? 0) > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400 dark:text-gray-500' }}">-₡{{ number_format($employee['ccss'] ?? 0, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summary Section -->
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-medium text-gray-700 dark:text-white uppercase tracking-wide border-b border-gray-200 dark:border-gray-700 pb-2">Resumen</h5>
                                        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-green-800 dark:text-green-300">Monto a pagar:</span>
                                                <span class="text-xl font-bold text-green-700 dark:text-green-400">₡{{ number_format($employee['salario_total'] ?? 0, 2) }}</span>
                                            </div>
                                            <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                Salario + Adicionales - Rebajas - CCSS
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
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 dark:from-green-600 dark:to-emerald-700 py-12 px-6 shadow-lg">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-col lg:flex-row items-center justify-between gap-8">
                        <!-- Left side - Title and period -->
                        <div class="text-center lg:text-left">
                            <h3 class="text-3xl font-bold text-white mb-2">Total a Pagar</h3>
                            <p class="text-white/90 text-lg">
                                {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}
                            </p>
                            <p class="text-white/80 text-sm mt-1">Monto total de la planilla</p>
                        </div>
                        
                        <!-- Right side - Amount -->
                        <div class="text-center lg:text-right">
                            <div class="text-7xl font-bold text-white mb-2">₡{{ number_format($totals['total_salario_total'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>