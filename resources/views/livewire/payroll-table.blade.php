<div>
    @if($employees->isEmpty())
        <div class="text-center py-8 text-gray-500">
            @if($dateFrom && $dateTo)
                No hay empleados con horas trabajadas en el período seleccionado
            @else
                Seleccione las fechas en el paso anterior para ver los empleados
            @endif
        </div>
    @else
        <div class="w-full overflow-x-auto bg-white rounded-lg shadow-sm border border-gray-200">
            <table class="w-full min-w-full divide-y divide-gray-200" id="payroll-table">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Empleado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200" title="N: Noches, R: Regulares, E: Extra">Horas</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Tarifa/h</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Salario</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Adicionales</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Rebajos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">CCSS</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Final</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($employees as $employee)
                        @php
                            $totalHoursEmp = $employee->timesheets->sum('hours');
                            $totalExtraHoursEmp = $employee->timesheets->sum('extra_hours');
                            $totalNightDaysEmp = $employee->timesheets->where('night_work', true)->count();
                            $hourlyRate = $employee->hourly_salary ?? 0;
                            $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
                            $salarioBase = ($totalHoursEmp * $hourlyRate) + ($totalExtraHoursEmp * $hourlyRate * 1.5) + ($totalNightDaysEmp * $nightWorkBonus);
                        @endphp
                        
                        <tr class="hover:bg-blue-50 transition-colors duration-150">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 border-r border-gray-300">{{ $employee->name }}</td>
                            <td class="px-3 py-1 text-sm text-gray-900 text-center border-r border-gray-300">
                                <div class="flex flex-col gap-0.5">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Regulares</span>
                                        <span class="text-sm text-gray-900">{{ number_format($totalHoursEmp, 1) }}h</span>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Extra</span>
                                        <span class="text-sm text-gray-900">{{ number_format($totalExtraHoursEmp, 1) }}h</span>
                                    </div>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Noches</span>
                                        <span class="text-sm text-gray-900">{{ $totalNightDaysEmp }}d</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900 text-center border-r border-gray-300">₡{{ number_format($hourlyRate, 2) }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900 text-center border-r border-gray-300">₡{{ number_format($salarioBase, 2) }}</td>
                            <td class="px-3 py-2 text-center border-r border-gray-300">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
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
                            <td class="px-3 py-2 text-center border-r border-gray-300">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
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
                            <td class="px-3 py-2 text-center border-r border-gray-300">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
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
                            <td class="px-3 py-2 text-sm text-gray-900 text-center font-bold border-r border-gray-300">
                                ₡{{ number_format($employeeTotals[$employee->id]['total_final'] ?? $salarioBase, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gradient-to-r from-slate-50 via-gray-50 to-slate-50 border-t-2 border-slate-200">
                    <tr style="background-color: #c4c9ce55;">
                        <td colspan="8" class="px-4 py-2">
                            <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                                <span>{{ $employees->count() }} empleado{{ $employees->count() !== 1 ? 's' : '' }} registrado{{ $employees->count() !== 1 ? 's' : '' }}</span>
                                <span>Período: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-4 text-sm font-bold text-slate-800 bg-slate-100 border-r border-slate-200">
                            TOTALES GENERALES
                        </td>
                        <td class="px-3 py-2 text-center bg-slate-50 border-r border-slate-200">
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Regulares</span>
                                    <span class="text-sm font-bold text-slate-800">{{ number_format($employees->sum(function($emp) { return $emp->timesheets->sum('hours'); }), 1) }}h</span>
                                </div>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Extra</span>
                                    <span class="text-sm font-bold text-slate-800">{{ number_format($employees->sum(function($emp) { return $emp->timesheets->sum('extra_hours'); }), 1) }}h</span>
                                </div>
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Noches</span>
                                    <span class="text-sm font-bold text-slate-800">{{ $employees->sum(function($emp) { return $emp->timesheets->where('night_work', true)->count(); }) }}d</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 border-r border-slate-200">
                            <span class="text-sm font-medium text-slate-500">N/A</span>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 border-r border-slate-200">
                            <div class="text-sm font-bold text-slate-800">
                                ₡{{ number_format($employees->sum(function($emp) { 
                                    $totalHoursEmp = $emp->timesheets->sum('hours');
                                    $totalExtraHoursEmp = $emp->timesheets->sum('extra_hours');
                                    $totalNightDaysEmp = $emp->timesheets->where('night_work', true)->count();
                                    $hourlyRate = $emp->hourly_salary ?? 0;
                                    $nightWorkBonus = \App\Models\GlobalConfig::getValue('night_work_bonus', 0);
                                    return ($totalHoursEmp * $hourlyRate) + ($totalExtraHoursEmp * $hourlyRate * 1.5) + ($totalNightDaysEmp * $nightWorkBonus);
                                }), 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 border-r border-slate-200">
                            <div class="text-sm font-bold text-slate-800">
                                ₡{{ number_format($this->grandTotals['adicionales'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 border-r border-slate-200">
                            <div class="text-sm font-bold text-slate-800">
                                ₡{{ number_format($this->grandTotals['rebajos'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-slate-50 border-r border-slate-200">
                            <div class="text-sm font-bold text-slate-800">
                                ₡{{ number_format($this->grandTotals['ccss'], 2) }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-center bg-gradient-to-r from-emerald-50 to-green-50 border-l-2 border-emerald-300">
                            <div class="text-lg font-bold text-emerald-800">
                                ₡{{ number_format($this->grandTotals['total_final'], 2) }}
                            </div>
                            <div class="text-xs text-emerald-600 font-medium mt-1">TOTAL FINAL</div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
