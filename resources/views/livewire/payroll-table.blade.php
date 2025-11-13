<div>
    @if($employees->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            @if($dateFrom && $dateTo)
                No hay empleados con horas trabajadas en el período seleccionado
            @else
                Seleccione las fechas en el paso anterior para ver los empleados
            @endif
        </div>
    @else
        <div class="w-full overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="payroll-table">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">Empleado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700" title="N: Noches, R: Regulares, E: Extra">Horas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">Tarifa/h</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">Salario</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">Adicionales</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">Rebajos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-700">CCSS</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">Total Final</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($employees as $employee)
                        @php
                            // Use stored values from employeeTotals to prevent recalculation on every render
                            $employeeData = $employeeTotals[$employee->id] ?? [];
                            $totalHoursEmp = $employeeData['total_hours'] ?? 0;
                            $totalExtraHoursEmp = $employeeData['total_extra_hours'] ?? 0;
                            $totalNightDaysEmp = $employeeData['total_night_days'] ?? 0;
                            $hourlyRate = $employeeData['hourly_rate'] ?? ($employee->hourly_salary ?? 0);
                            $salarioBase = $employeeData['salario_base'] ?? 0;
                        @endphp
                        
                        <tr class="hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors duration-150">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-700">{{ $employee->name }}</td>
                            <td class="px-3 py-1 text-sm text-gray-900 dark:text-gray-100 text-center border-r border-gray-300 dark:border-gray-700">
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Regulares</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ number_format($totalHoursEmp, 1) }}h</span>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Extra</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ number_format($totalExtraHoursEmp, 1) }}h</span>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Noches</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $totalNightDaysEmp }}d</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100 text-center border-r border-gray-300 dark:border-gray-700">₡{{ number_format($hourlyRate, 2) }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100 text-center border-r border-gray-300 dark:border-gray-700">₡{{ number_format($salarioBase, 2) }}</td>
                            <td class="px-3 py-2 text-center border-r border-gray-300 dark:border-gray-700">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600 dark:text-gray-400">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employee->id }}.adicionales"
                                            placeholder="0.00"
                                            class="text-center pl-8"
                                            min="0"
                                            step="0.01"
                                            x-data="{}"
                                            x-on:input="$el.value = $el.value.replace(/[^0-9.,]/g, '').replace(/(\..*)\./g, '$1')"
                                            title="Ingrese un valor mayor o igual a 0" />
                                    </x-filament::input.wrapper>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center border-r border-gray-300 dark:border-gray-700">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600 dark:text-gray-400">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employee->id }}.rebajos"
                                            placeholder="0.00"
                                            class="text-center pl-8"
                                            min="0"
                                            step="0.01"
                                            x-data="{}"
                                            x-on:input="$el.value = $el.value.replace(/[^0-9.,]/g, '').replace(/(\..*)\./g, '$1')"
                                            title="Ingrese un valor mayor o igual a 0" />
                                    </x-filament::input.wrapper>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center border-r border-gray-300 dark:border-gray-700">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600 dark:text-gray-400">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employee->id }}.ccss"
                                            placeholder="0.00"
                                            class="text-center pl-8"
                                            min="0"
                                            step="0.01"
                                            x-data="{}"
                                            x-on:input="$el.value = $el.value.replace(/[^0-9.,]/g, '').replace(/(\..*)\./g, '$1')"
                                            title="Ingrese un valor mayor o igual a 0" />
                                    </x-filament::input.wrapper>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100 text-center font-bold border-r border-gray-300 dark:border-gray-700">
                                ₡{{ number_format($employeeTotals[$employee->id]['total_final'] ?? ($employeeData['salario_base'] ?? 0), 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gradient-to-r from-slate-50 via-gray-50 to-slate-50 dark:from-gray-700 dark:via-gray-800 dark:to-gray-700 border-t-2 border-slate-200 dark:border-gray-700">
                    <tr>
                        <td colspan="8" class="px-4 py-2 bg-gray-100 dark:bg-gray-800" x-data x-bind:style="$store.theme === 'dark' ? 'background-color: rgb(31 41 55) !important;' : 'background-color: rgb(243 244 246) !important;'">
                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-gray-300 font-medium">
                                <span>{{ $employees->count() }} empleado{{ $employees->count() !== 1 ? 's' : '' }} registrado{{ $employees->count() !== 1 ? 's' : '' }}</span>
                                <span>Período: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="border-t border-slate-200 dark:border-gray-700">
                        <td class="px-4 py-4 text-sm font-bold text-slate-800 dark:text-gray-100 bg-slate-100 dark:bg-gray-700 border-r border-slate-200 dark:border-gray-600">
                            TOTALES GENERALES
                        </td>
                        <td class="px-3 py-2 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Regulares</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-gray-100">{{ number_format(collect($employeeTotals)->sum('total_hours'), 1) }}h</span>
                                </div>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Extra</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-gray-100">{{ number_format(collect($employeeTotals)->sum('total_extra_hours'), 1) }}h</span>
                                </div>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Noches</span>
                                    <span class="text-sm font-bold text-slate-800 dark:text-gray-100">{{ collect($employeeTotals)->sum('total_night_days') }}d</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <span class="text-sm font-medium text-slate-500 dark:text-gray-400">N/A</span>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <div class="text-sm font-bold text-slate-800 dark:text-gray-100">
                                ₡{{ number_format(collect($employeeTotals)->sum('salario_base'), 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <div class="text-sm font-bold text-slate-800 dark:text-gray-100">
                                ₡{{ number_format($this->grandTotals['adicionales'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <div class="text-sm font-bold text-slate-800 dark:text-gray-100">
                                ₡{{ number_format($this->grandTotals['rebajos'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700">
                            <div class="text-sm font-bold text-slate-800 dark:text-gray-100">
                                ₡{{ number_format($this->grandTotals['ccss'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/30 dark:to-green-900/30 border-l-2 border-emerald-300 dark:border-emerald-600">
                            <div class="text-lg font-bold text-emerald-800 dark:text-emerald-300">
                                ₡{{ number_format($this->grandTotals['total_final'], 2) }}
                            </div>
                            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1">TOTAL FINAL</div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
