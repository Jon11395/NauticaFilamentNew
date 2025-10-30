<div>
    <!-- Header Section -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">Planilla de Salarios Fijos</h1>
                <p class="mt-2 text-sm text-gray-600">Gestione los salarios fijos de los empleados seleccionados</p>
            </div>
            <x-filament::button
                wire:click="openEmployeeModal"
                color="primary"
                size="sm"
                icon="heroicon-o-plus"
                class="w-auto max-w-fit"
            >
                Agregar Empleados
            </x-filament::button>
        </div>
    </div>

    <!-- Filament Modal -->
    <x-filament::modal id="employee-selection-modal" width="sm" class="max-w-lg mx-auto">
        <x-slot name="heading">
            <div class="text-lg font-semibold text-gray-900">Seleccionar Empleados</div>
        </x-slot>
        
        <x-slot name="description">
            <div class="text-sm text-gray-600">Selecciona los empleados que deseas agregar a la planilla de salarios fijos</div>
        </x-slot>
        
        <!-- Search Input -->
        <div class="mb-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    placeholder="Buscar empleados..."
                    wire:model.live.debounce.300ms="employeeSearch"
                    class="w-full"
                />
            </x-filament::input.wrapper>
        </div>
        
        <!-- Employee List -->
        <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
            @if($this->getFilteredEmployees()->count() > 0)
                @foreach($this->getFilteredEmployees() as $employee)
                    <div 
                        class="flex px-3 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors touch-manipulation"
                        wire:click="toggleEmployeeSelection({{ $employee->id }})"
                    >
                        <div class="flex items-center mr-4">
                            <input 
                                type="checkbox" 
                                {{ in_array($employee->id, $selectedEmployeesToAdd ?? []) ? 'checked' : '' }}
                                wire:click="toggleEmployeeSelection({{ $employee->id }})"
                                class="w-4 h-4 text-primary-600 bg-white border-gray-300 rounded focus:ring-primary-500 focus:ring-2 opacity-0 absolute"
                                onclick="event.stopPropagation()"
                            />
                            <div class="w-4 h-4 border border-gray-300 rounded flex items-center justify-center {{ in_array($employee->id, $selectedEmployeesToAdd ?? []) ? 'bg-primary-600 border-primary-600' : 'bg-white' }}">
                                @if(in_array($employee->id, $selectedEmployeesToAdd ?? []))
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center min-w-0 flex-1">
                            <div class="flex flex-col" style="margin-left: 8px;">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ $employee->name }}</div>
                                @if($employee->function)
                                    <div class="text-xs text-gray-500">{{ $employee->function }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-8 text-gray-500">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 mb-4">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">No se encontraron empleados</h3>
                    <p class="text-xs text-gray-500">
                        @if(!empty($employeeSearch))
                            No hay empleados que coincidan con "{{ $employeeSearch }}"
                        @else
                            No hay empleados disponibles para agregar
                        @endif
                    </p>
                </div>
            @endif
        </div>
        
        <x-slot name="footerActions">
            <x-filament::button
                wire:click="addSelectedEmployees"
                color="primary"
                class="w-full sm:w-auto"
                :disabled="empty($selectedEmployeesToAdd)"
            >
                Agregar {{ count($selectedEmployeesToAdd) > 0 ? '(' . count($selectedEmployeesToAdd) . ')' : '' }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    @if($employees->isEmpty())
        <div class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px;">
            <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay empleados seleccionados</h3>
            <p class="mt-1 text-sm text-gray-500">Comience agregando empleados a la planilla.</p>
            <div class="mt-6">
                <button wire:click="openModal" 
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Agregar Empleados
                </button>
            </div>
        </div>
    @else
        <div class="w-full bg-white rounded-lg shadow-sm border border-gray-200">
            <table class="w-full divide-y divide-gray-200" id="fixed-payroll-table">
                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Empleado</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Salario Base</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Adicionales</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Rebajos</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">CCSS</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Final</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($employees as $employee)
                        @php
                            $employeeId = $employee->id;
                            $employeeName = $employee->name;
                            $salarioBase = $employeeTotals[$employeeId]['salario_base'] ?? 0;
                            $adicionales = $employeeTotals[$employeeId]['adicionales'] ?? 0;
                            $rebajos = $employeeTotals[$employeeId]['rebajos'] ?? 0;
                            $ccss = $employeeTotals[$employeeId]['ccss'] ?? 0;
                            $totalFinal = $employeeTotals[$employeeId]['total_final'] ?? 0;
                        @endphp
                        
                        <tr class="hover:bg-blue-50 transition-colors duration-150 group">
                            <td class="px-3 py-2 text-sm font-bold text-gray-900 border-r border-gray-300">
                                <div class="flex items-center justify-between">
                                    <span>{{ $employeeName }}</span>
                                    <button wire:click="removeEmployee({{ $employeeId }})" 
                                            type="button"
                                            class="opacity-30 group-hover:opacity-100 text-red-600 hover:text-red-800 transition-opacity duration-200 p-1 rounded-full hover:bg-red-100"
                                            title="Remover empleado de la planilla"
                                            onclick="event.preventDefault(); event.stopPropagation();">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #dc2626;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center border-r border-gray-200">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employeeId }}.salario_base"
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
                            <td class="px-3 py-2 text-center border-r border-gray-200">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employeeId }}.adicionales"
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
                            <td class="px-3 py-2 text-center border-r border-gray-200">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employeeId }}.rebajos"
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
                            <td class="px-3 py-2 text-center border-r border-gray-200">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-sm font-medium text-gray-600">₡</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input 
                                            type="text" 
                                            wire:model.blur="employeeTotals.{{ $employeeId }}.ccss"
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
                            <td class="px-3 py-2 text-center bg-green-50">
                                <div class="text-sm font-bold text-green-700">
                                    ₡{{ number_format($totalFinal, 2) }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gradient-to-r from-slate-50 via-gray-50 to-slate-50 border-t-2 border-slate-200">
                    <tr style="background-color: #c4c9ce55;">
                        <td colspan="6" class="px-4 py-2">
                            <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                                <span>{{ $employees->count() }} empleado{{ $employees->count() !== 1 ? 's' : '' }} registrado{{ $employees->count() !== 1 ? 's' : '' }}</span>
                                <span>Período: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : 'N/A' }} - {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'N/A' }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="border-t border-slate-200">
                        <td colspan="5" class="px-4 py-4 text-sm font-bold text-slate-800 bg-slate-100 border-r border-slate-200">
                            TOTAL GENERAL
                        </td>
                        <td class="px-3 py-4 text-center bg-gradient-to-r from-emerald-50 to-green-50 border-l-2 border-emerald-300">
                            <div class="text-lg font-bold text-emerald-800">
                                ₡{{ number_format($this->grandTotals['total_final'], 2) }}
                            </div>
                            <div class="text-xs text-emerald-600 font-medium mt-1">TOTAL SALARIOS</div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <!-- Modal for selecting employees -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white" wire:click.stop>
                <div class="mt-3">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Seleccionar Empleados</h3>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="mt-4">
                        <p class="text-sm text-gray-600 mb-4">Seleccione los empleados que desea agregar a la planilla:</p>
                        
                        @if(count($availableEmployees) > 0)
                            <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                                @foreach($availableEmployees as $employee)
                                    <div class="flex items-center p-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                                        <input type="checkbox" 
                                               wire:model="selectedEmployees" 
                                               value="{{ $employee->id }}"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                                    <span class="text-white text-sm font-semibold">{{ substr($employee->name, 0, 1) }}</span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">{{ $employee->name }}</p>
                                                    <p class="text-xs text-gray-500">{{ $employee->function ?? 'Sin función asignada' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500">
                                <p>No hay empleados activos disponibles.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 mt-4">
                        <button wire:click="closeModal" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-200">
                            Cancelar
                        </button>
                        <button wire:click="addSelectedEmployees" 
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                            Agregar Seleccionados ({{ count($selectedEmployees) }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
