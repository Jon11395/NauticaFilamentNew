<div>
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Selección de Proyecto
            </x-slot>
            
            <x-slot name="description">
                Selecciona un proyecto para gestionar sus horas trabajadas
            </x-slot>
            
            <div class="max-w-md">
                <x-filament::input.wrapper>
                    <x-filament::input.select 
                        onchange="Livewire.find('{{ $this->getId() }}').handleProjectChange(this.value)"
                    >
                        <option value="">Selecciona un proyecto...</option>
                        @foreach(\App\Models\Project::all() as $project)
                            <option value="{{ $project->id }}" {{ $selectedProjectId == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-filament::section>
        
        @if($selectedProjectId)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="hidden sm:block">
                            <h3 class="text-lg font-medium text-gray-900">{{ $this->getProjectName() }}</h3>
                            <p class="text-sm text-gray-500">{{ $this->getCurrentPeriodName() }}</p>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                            <!-- Date Inputs -->
                            <div class="flex flex-col sm:flex-row gap-3 flex-1">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Desde</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input
                                            type="date"
                                            wire:model.live="startDate"
                                            placeholder="Selecciona fecha de inicio"
                                            class="w-full"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-500 mb-2">Hasta</label>
                                    <div class="flex items-center space-x-2">
                                        <x-filament::input.wrapper class="flex-1">
                                            <x-filament::input
                                                type="date"
                                                wire:model.live="endDate"
                                                disabled
                                                placeholder="Fecha calculada automáticamente"
                                                class="w-full"
                                            />
                                        </x-filament::input.wrapper>
                                        <div class="text-xs text-gray-500 bg-gray-200 px-3 py-2 rounded-lg whitespace-nowrap">
                                           + 15 días
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <div class="flex justify-center sm:justify-end mt-2 sm:mt-0">
                                <x-filament::button
                                    wire:click="goToCurrentPeriod"
                                    size="xs"
                                    color="primary"
                                    tooltip="Ir al período actual (hoy + 13 días anteriores)"
                                    class="w-full sm:w-auto sm:max-w-fit flex items-center justify-center gap-1"
                                >
                                    Hoy
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </x-slot>
                
                <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
                    <div class="min-w-full">
                        <!-- Employee Management Header -->
                        <div class="bg-gray-50 border-b border-gray-200" style="width: calc(220px + 15 * 65px);">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0 p-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-semibold text-gray-900">Empleados del Proyecto</h4>
                                    <p class="text-sm text-gray-500 mt-1">Gestiona los empleados asignados a este proyecto</p>
                                </div>
                                <div class="flex justify-start lg:justify-end">
                                    <div class="w-auto">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Seleccionar Empleados
                                        </label>
                                        
                                        <!-- Modal Trigger Button -->
                                        <x-filament::button
                                            wire:click="openEmployeeModal"
                                            color="primary"
                                            size="sm"
                                            icon="heroicon-o-plus"
                                            class="w-auto max-w-fit"
                                            onclick="setTimeout(() => { document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false); }, 100)"
                                        >Agregar Empleados
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendar Header -->
                        <div class="grid gap-0 mb-0 min-w-max" style="grid-template-columns: 220px repeat(15, 65px);">
                            <!-- Employee Name Column Header -->
                            <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-r border-gray-200 font-semibold text-sm text-gray-700 sticky left-0 z-10">
                                <div class="flex items-center">
                                    <x-heroicon-o-user class="w-4 h-4 mr-2" />
                                    <span>Empleado</span>
                                </div>
                            </div>
                            <!-- Day Headers -->
                            @foreach($this->getPeriodDays() as $index => $currentDate)
                                @php
                                    $isWeekend = $currentDate->isWeekend();
                                    $isToday = $currentDate->isToday();
                                @endphp
                                <div class="p-2 bg-gradient-to-b {{ $isWeekend ? 'from-red-50 to-red-100 text-red-700' : ($isToday ? 'from-blue-50 to-blue-100 text-blue-700' : 'from-gray-50 to-gray-100 text-gray-700') }} border-b border-r border-gray-200 text-center font-medium {{ $isToday ? 'font-bold' : '' }}">
                                    <div class="text-xs opacity-75 hidden sm:block">{{ $currentDate->locale('es')->isoFormat('ddd') }}</div>
                                    <div class="text-sm font-semibold hidden sm:block">{{ $currentDate->format('d') }}</div>
                                    <div class="text-xs opacity-60 font-normal hidden lg:block">{{ $currentDate->locale('es')->isoFormat('MMM') }}</div>
                                    <!-- Mobile: Show day/month -->
                                    <div class="text-xs opacity-75 sm:hidden">{{ $currentDate->locale('es')->isoFormat('ddd') }}</div>
                                    <div class="text-xs font-medium sm:hidden">{{ $currentDate->format('d/m') }}</div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Calendar Body -->
                        @foreach($this->getProjectEmployees() as $employee)
                            <div class="group grid gap-0 border-b border-gray-200 hover:bg-gray-50 min-w-max" style="grid-template-columns: 220px repeat(15, 65px);">
                                <!-- Employee Name -->
                                <div class="p-4 bg-white border-r border-gray-200 text-sm font-medium text-gray-900 sticky left-0 z-10 hover:bg-gray-50 transition-colors">
                                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-0">
                                        @php
                                            $totals = $this->getEmployeeTotalsForPeriod($employee->id);
                                        @endphp
                                        
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                               
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-sm font-semibold text-gray-900">{{ $employee->name }}</div>
                                                    <div class="text-xs mt-0.5 space-y-0.5">
                                                        @if($employee->hourly_salary && $employee->hourly_salary > 0)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: #dcfce7; color: #166534;">
                                                                ₡{{ number_format($employee->hourly_salary, 2) }}/h
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="background-color: #fee2e2; color: #dc2626;">
                                                                Sin salario
                                                            </span>
                                                        @endif
                                                        
                                                        
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <x-filament::button
                                            wire:click="removeEmployeeFromProject({{ $employee->id }})"
                                            size="xs"
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            tooltip="Remover del proyecto"
                                            class="self-end lg:self-center"
                                        />
                                    </div>
                                    
                                    
                                    
                                    <!-- Desktop: Show full details in compact grid layout -->
                                    <div class="mt-0 hidden lg:block">
                                        <div class="grid grid-cols-2 gap-0.5 text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Regular:</span>
                                                <span class="font-medium {{ $totals['regular_hours'] > 0 ? 'text-green-600' : 'text-gray-400' }}">
                                                    {{ number_format($totals['regular_hours'], 1) }}h
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Extra:</span>
                                                <span class="font-medium {{ $totals['extra_hours'] > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                                                    {{ number_format($totals['extra_hours'], 1) }}h
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Noche:</span>
                                                <span class="font-medium {{ $totals['night_work_days'] > 0 ? 'text-purple-600' : 'text-gray-400' }}">
                                                    {{ $totals['night_work_days'] }}d
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total:</span>
                                                <span class="font-bold text-primary-600">
                                                    {{ number_format($totals['total_hours'], 1) }}h
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-0.5 pt-0.5 border-t border-gray-200">
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-700 font-medium">Costo:</span>
                                                <span class="font-bold text-green-600">
                                                    ₡{{ number_format($totals['total_cost'], 0) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mobile: Compact card view -->
                                    <div class="mt-0 lg:hidden">
                                        <div class="bg-gray-50 rounded-md p-2 text-xs">
                                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                                <span>Reg:{{ number_format($totals['regular_hours'], 1) }}h</span>
                                                <span>Ext:{{ number_format($totals['extra_hours'], 1) }}h</span>
                                                <span>Noc:{{ $totals['night_work_days'] }}d</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-blue-600">{{ number_format($totals['total_hours'], 1) }}h</span>
                                                <span class="font-semibold text-green-600">Costo: ₡{{ number_format($totals['total_cost'], 0) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Day Cells -->
                                @foreach($this->getPeriodDays() as $currentDate)
                                    @php
                                        $timesheet = $this->getTimesheetForEmployeeAndDate($employee->id, $currentDate);
                                        $isWeekend = $currentDate->isWeekend();
                                        $isToday = $currentDate->isToday();
                                        $hasHours = $timesheet && ($timesheet->hours > 0 || $timesheet->extra_hours > 0);
                                    @endphp
                                    <div class="p-1 bg-white border-r border-gray-200 flex flex-col justify-center group hover:bg-gray-50 transition-colors {{ $isWeekend ? 'bg-red-25' : '' }} {{ $isToday ? 'bg-blue-25' : '' }} {{ $hasHours ? 'bg-green-25' : '' }}">
                                        <div class="space-y-1">
                                            <!-- Regular Hours -->
                                            <div class="space-y-1">
                                                <div class="text-xs text-gray-500 text-center leading-none hidden lg:block font-medium">R</div>
                                                <input 
                                                    type="number" 
                                                    step="0.5" 
                                                    min="0" 
                                                    max="24" 
                                                    value="{{ $timesheet ? $timesheet->hours : '' }}"
                                                    id="regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                    wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', $event.target.value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
                                                    class="w-full h-6 text-xs text-center border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 rounded-md px-1 py-1 transition-all {{ $timesheet && $timesheet->hours > 0 ? 'bg-green-50 border-green-300 text-green-700' : '' }} {{ $isWeekend ? 'bg-red-50 border-red-300' : '' }} {{ $isToday ? 'bg-blue-50 border-blue-300' : '' }} hover:shadow-sm"
                                                    placeholder="0"
                                                    title="Horas regulares - {{ $currentDate->format('d/m/Y') }} - {{ $employee->name }}"
                                                />
                                            </div>
                                            <!-- Extra Hours -->
                                            <div class="space-y-1">
                                                <div class="text-xs text-gray-500 text-center leading-none hidden lg:block font-medium">E</div>
                                                <input 
                                                    type="number" 
                                                    step="0.5" 
                                                    min="0" 
                                                    max="24" 
                                                    value="{{ $timesheet ? $timesheet->extra_hours : '' }}"
                                                    id="extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                    wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
                                                    class="w-full h-6 text-xs text-center border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 rounded-md px-1 py-1 transition-all {{ $timesheet && $timesheet->extra_hours > 0 ? 'bg-orange-50 border-orange-300 text-orange-700' : '' }} {{ $isWeekend ? 'bg-red-50 border-red-300' : '' }} {{ $isToday ? 'bg-blue-50 border-blue-300' : '' }} hover:shadow-sm"
                                                    placeholder="0"
                                                    title="Horas extra - {{ $currentDate->format('d/m/Y') }} - {{ $employee->name }}"
                                                />
                                            </div>
                                            <!-- Night Work Checkbox -->
                                            <div class="space-y-1">
                                                <div class="text-xs text-gray-500 text-center leading-none hidden lg:block font-medium">
                                                    N
                                                </div>
                                                <div class="flex items-center justify-center">
                                                    <input 
                                                        type="checkbox" 
                                                        id="night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                        {{ $timesheet && $timesheet->night_work ? 'checked' : '' }}
                                                        wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.checked)"
                                                        class="w-4 h-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded transition-all hover:scale-110"
                                                        title="Trabajo nocturno - {{ $currentDate->format('d/m/Y') }} - {{ $employee->name }}"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                        
                        @if($this->getProjectEmployees()->count() == 0)
                            <div class="text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100 border-t border-gray-200">
                                <div class="max-w-md mx-auto">
                                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <x-heroicon-o-users class="w-10 h-10 text-blue-600" />
                                    </div>
                                    <h5 class="text-xl font-semibold text-gray-900 mb-3">No hay empleados asignados</h5>
                                    <p class="text-sm text-gray-600 mb-6">Selecciona empleados de la lista para agregarlos al proyecto y comenzar a registrar sus horas</p>
                                    <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                                        <div class="flex items-center justify-center space-x-2 text-xs text-gray-500">
                                            <x-heroicon-o-light-bulb class="w-4 h-4" />
                                            <span>Tip: Usa el botón "Seleccionar Empleados" para agregar trabajadores</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                
                
                <!-- Summary Section -->
                <div class="mt-8">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Resumen del Período</h3>
                                <p class="text-sm text-gray-600 mt-1">Estadísticas del período seleccionado</p>
                            </div>
                            <div class="hidden sm:block">
                                <div class="flex items-center space-x-2 text-sm text-gray-500">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    <span>Período activo</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Total Hours -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Horas Totales</p>
                                        <p class="text-2xl font-bold text-blue-600 mt-1">{{ $this->getTotalHoursForPeriod() }}</p>
                                    </div>
                                    <div class="p-3 bg-blue-100 rounded-full">
                                        <x-heroicon-o-clock class="w-6 h-6 text-blue-600" />
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-xs text-gray-500">
                                    <x-heroicon-o-arrow-trending-up class="w-3 h-3 mr-1" />
                                    <span>Registradas en el período</span>
                                </div>
                            </div>
                            
                            <!-- Total Cost -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Costo Total</p>
                                        <p class="text-2xl font-bold text-green-600 mt-1">₡{{ number_format($this->getTotalCostForPeriod(), 0) }}</p>
                                    </div>
                                    <div class="p-3 bg-green-100 rounded-full">
                                        <x-heroicon-o-currency-dollar class="w-6 h-6 text-green-600" />
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-xs text-gray-500">
                                    <x-heroicon-o-banknotes class="w-3 h-3 mr-1" />
                                    <span>Salarios calculados</span>
                                </div>
                            </div>
                            
                            <!-- Total Employees -->
                            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">Empleados</p>
                                        <p class="text-2xl font-bold text-purple-600 mt-1">{{ $this->getProjectEmployees()->count() }}</p>
                                    </div>
                                    <div class="p-3 bg-purple-100 rounded-full">
                                        <x-heroicon-o-users class="w-6 h-6 text-purple-600" />
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-xs text-gray-500">
                                    <x-heroicon-o-user-group class="w-3 h-3 mr-1" />
                                    <span>Asignados al proyecto</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-o-folder-open class="w-8 h-8 text-gray-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Selecciona un Proyecto</h3>
                    <p class="text-gray-500 max-w-sm mx-auto text-sm">Elige un proyecto del menú desplegable para gestionar las horas trabajadas</p>
                    <div class="mt-4 flex items-center justify-center space-x-2 text-xs text-gray-400">
                        <x-heroicon-o-arrow-up class="w-3 h-3" />
                        <span>Usa el selector de arriba</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

<x-filament::modal id="employee-selection-modal" width="sm" class="max-w-lg mx-auto" :key="$modalRefreshKey">
    <x-slot name="heading">
        <div class="text-lg font-semibold text-gray-900">Seleccionar Empleados</div>
    </x-slot>
    
    <x-slot name="description">
        <div class="text-sm text-gray-600">Selecciona los empleados que deseas agregar al proyecto</div>
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
        @foreach($this->getFilteredEmployees() as $employee)
            <div 
                class="flex px-3 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors touch-manipulation"
                wire:click="toggleEmployeeSelection({{ $employee->id }})"
            >
                <div class="flex items-center mr-4">
                    <input 
                        type="checkbox" 
                        {{ in_array($employee->id, $selectedEmployeesToAdd ?? []) ? 'checked' : '' }}
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
                    <div class="text-sm font-medium text-gray-900 truncate">{{ $employee->name }}</div>
                </div>
            </div>
        @endforeach
    </div>
    
    <x-slot name="footerActions">
        <x-filament::button
            wire:click="addEmployeesToProject"
            color="primary"
            class="w-full sm:w-auto"
            :disabled="empty($selectedEmployeesToAdd)"
        >
            Agregar {{ count($selectedEmployeesToAdd) > 0 ? '(' . count($selectedEmployeesToAdd) . ')' : '' }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>

<!-- Employee Removal Confirmation Modal -->
<x-filament::modal id="remove-employee-confirmation-modal" width="sm" class="max-w-lg mx-auto">
    <x-slot name="heading">
        <div class="flex items-center">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 mr-2 flex-shrink-0" />
            <div class="text-lg font-semibold text-gray-900">Remover Empleado del Proyecto</div>
        </div>
    </x-slot>
    
    <x-slot name="description">
        <div class="space-y-3">
            <p class="text-sm lg:text-base text-gray-700">
                ¿Estás seguro de que deseas remover a <strong>{{ $this->getEmployeeToRemoveName() }}</strong> del proyecto?
            </p>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <div class="flex flex-col sm:flex-row">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mb-2 sm:mb-0" />
                    <div class="text-xs lg:text-sm text-yellow-700">
                        <p class="font-medium mb-1">Esta acción:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Removerá al empleado del proyecto</li>
                            <li>Mantendrá todas sus horas registradas</li>
                            <li>Permitirá restaurar al empleado más tarde</li>
                        </ul>
                        <p class="font-medium mt-2">Las horas registradas se restaurarán automáticamente si vuelve a agregarse al proyecto.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
    
    <x-slot name="footerActions">
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <x-filament::button
                wire:click="confirmRemoveEmployee({{ $employeeToRemove }})"
                color="danger"
                icon="heroicon-o-trash"
                class="w-full sm:w-auto"
            >
                Sí, Remover
            </x-filament::button>
            
            <x-filament::button
                wire:click="$dispatch('close-modal', { id: 'remove-employee-confirmation-modal' })"
                color="gray"
                class="w-full sm:w-auto"
            >
                Cancelar
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>
</div>