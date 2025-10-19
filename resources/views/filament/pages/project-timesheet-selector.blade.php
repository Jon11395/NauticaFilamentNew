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
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $this->getProjectName() }}</h3>
                            <p class="text-sm text-gray-500">{{ $this->getCurrentPeriodName() }}</p>
                        </div>
                        <div class="flex items-center">
                            <div class="flex items-center space-x-2 mr-6 p-2 bg-gray-50 rounded-lg">
                                <label class="text-sm font-medium text-gray-700">Desde:</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="date"
                                        wire:model.live="startDate"
                                        placeholder="Selecciona fecha de inicio"
                                    />
                                </x-filament::input.wrapper>
                            </div>
                            <div class="flex items-center space-x-2 mr-6 p-2 bg-gray-50 rounded-lg">
                                <label class="text-sm font-medium text-gray-500">Hasta:</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="date"
                                        wire:model.live="endDate"
                                        disabled
                                        placeholder="Fecha calculada automáticamente"
                                    />
                                </x-filament::input.wrapper>
                                <div class="text-xs text-gray-500 bg-gray-200 px-3 py-2 rounded-lg mr-8">
                                   + 15 días
                                </div>
                            </div>
                            <div class="border-l border-gray-200 h-6 mx-4"></div>
                            <x-filament::button
                                wire:click="goToCurrentPeriod"
                                size="sm"
                                color="primary"
                                tooltip="Ir al período actual (hoy + 13 días anteriores)"
                                class="ml-4"
                            >
                                Hoy
                            </x-filament::button>
                        </div>
                    </div>
                </x-slot>
                
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <div class="min-w-full">
                        <!-- Employee Management Header -->
                        <div class="p-4 bg-gray-50 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">Empleados del Proyecto</h4>
                                    <p class="text-sm text-gray-500 mt-1">Gestiona los empleados asignados a este proyecto</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="w-96">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Seleccionar Empleados
                                        </label>
                                        
                                        <!-- Modal Trigger Button -->
                                        <x-filament::button
                                            wire:click="openEmployeeModal"
                                            color="primary"
                                            size="sm"
                                            icon="heroicon-o-plus"
                                            onclick="setTimeout(() => { document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false); }, 100)"
                                        >Seleccionar Empleados
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendar Header -->
                        <div class="grid gap-0 mb-0" style="grid-template-columns: 280px repeat(15, 1fr);">
                            <!-- Employee Name Column Header -->
                            <div class="p-4 bg-gray-50 border-b border-r border-gray-200 font-semibold text-sm text-gray-700 sticky left-0 z-10">
                                Empleado
                            </div>
                            <!-- Day Headers -->
                            @foreach($this->getPeriodDays() as $index => $currentDate)
                                @php
                                    $isWeekend = $currentDate->isWeekend();
                                    $isToday = $currentDate->isToday();
                                @endphp
                                <div class="p-2 bg-gray-50 border-b border-r border-gray-200 text-center font-medium text-xs text-gray-700 {{ $isWeekend ? 'bg-red-50 text-red-600' : '' }} {{ $isToday ? 'bg-blue-50 text-blue-600 font-bold' : '' }}">
                                    <div>{{ $currentDate->format('d') }}</div>
                                    <div class="text-xs opacity-75">{{ $currentDate->locale('es')->isoFormat('ddd') }}</div>
                                    <div class="text-xs opacity-60 font-normal">{{ $currentDate->locale('es')->isoFormat('MMM') }}</div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Calendar Body -->
                        @foreach($this->getProjectEmployees() as $employee)
                            <div class="grid gap-0 border-b border-gray-200 hover:bg-gray-50" style="grid-template-columns: 280px repeat(15, 1fr);">
                                <!-- Employee Name -->
                                <div class="p-4 bg-white border-r border-gray-200 text-sm font-medium text-gray-900 sticky left-0 z-10 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="truncate">{{ $employee->name }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                @if($employee->hourly_salary && $employee->hourly_salary > 0)
                                                    ₡{{ number_format($employee->hourly_salary, 2) }}/h
                                                @else
                                                    <span class="text-red-500">Sin salario por hora</span>
                                                @endif
                                            </div>
                                        </div>
                                        <x-filament::button
                                            wire:click="removeEmployeeFromProject({{ $employee->id }})"
                                            size="xs"
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            tooltip="Remover del proyecto"
                                            class="ml-2"
                                        />
                                    </div>
                                    
                                    @php
                                        $totals = $this->getEmployeeTotalsForPeriod($employee->id);
                                    @endphp
                                    
                                    <div class="mt-2 space-y-1 text-xs">
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
                                        <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                                            <span class="text-gray-700 font-medium">Total:</span>
                                            <span class="font-bold text-primary-600">
                                                {{ number_format($totals['total_hours'], 1) }}h
                                            </span>
                                        </div>
                                        <div class="flex justify-between border-t border-gray-200 pt-1 mt-1">
                                            <span class="text-gray-700 font-medium">Costo:</span>
                                            <span class="font-bold text-green-600">
                                                ₡{{ number_format($totals['total_cost'], 0) }}
                                            </span>
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
                                    <div class="p-1 bg-white border-r border-gray-200 flex flex-col justify-center {{ $isWeekend ? 'bg-red-25' : '' }} {{ $isToday ? 'bg-blue-25' : '' }} {{ $hasHours ? 'bg-green-25' : '' }}">
                                        <div class="space-y-1">
                                            <!-- Regular Hours -->
                                            <div class="space-y-0.5">
                                                <div class="text-xs text-gray-500 text-center leading-none">R</div>
                                                <input 
                                                    type="number" 
                                                    step="0.5" 
                                                    min="0" 
                                                    max="24" 
                                                    value="{{ $timesheet ? $timesheet->hours : '' }}"
                                                    id="regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                    wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', $event.target.value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
                                                    class="w-full h-5 text-xs text-center border border-gray-300 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 rounded px-1 py-0.5 {{ $timesheet && $timesheet->hours > 0 ? 'bg-green-50 border-green-300' : '' }} {{ $isWeekend ? 'bg-red-50 border-red-300' : '' }} {{ $isToday ? 'bg-blue-50 border-blue-300' : '' }}"
                                                    placeholder="0"
                                                    title="Horas regulares - {{ $currentDate->format('d/m/Y') }} - {{ $employee->name }}"
                                                />
                                            </div>
                                            <!-- Extra Hours -->
                                            <div class="space-y-0.5">
                                                <div class="text-xs text-gray-500 text-center leading-none">E</div>
                                                <input 
                                                    type="number" 
                                                    step="0.5" 
                                                    min="0" 
                                                    max="24" 
                                                    value="{{ $timesheet ? $timesheet->extra_hours : '' }}"
                                                    id="extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                    wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
                                                    class="w-full h-5 text-xs text-center border border-gray-300 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 rounded px-1 py-0.5 {{ $timesheet && $timesheet->extra_hours > 0 ? 'bg-orange-50 border-orange-300' : '' }} {{ $isWeekend ? 'bg-red-50 border-red-300' : '' }} {{ $isToday ? 'bg-blue-50 border-blue-300' : '' }}"
                                                    placeholder="0"
                                                    title="Horas extra - {{ $currentDate->format('d/m/Y') }} - {{ $employee->name }}"
                                                />
                                            </div>
                                            <!-- Night Work Checkbox -->
                                            <div class="space-y-0.5">
                                                <div class="text-xs text-gray-500 text-center leading-none">
                                                    N
                                                </div>
                                                <div class="flex items-center justify-center">
                                                    <input 
                                                        type="checkbox" 
                                                        id="night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}"
                                                        {{ $timesheet && $timesheet->night_work ? 'checked' : '' }}
                                                        wire:blur="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.checked)"
                                                        class="w-3 h-3 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
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
                            <div class="text-center py-12 bg-gray-50 border-t border-gray-200">
                                <x-heroicon-o-users class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                                <h5 class="text-xl font-medium text-gray-900 mb-2">No hay empleados asignados</h5>
                                <p class="text-sm text-gray-500 mb-4">Selecciona empleados de la lista para agregarlos al proyecto</p>
                                <div class="text-xs text-gray-400">
                                    <p>• Escribe para buscar empleados por nombre</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                
                
                <!-- Summary Section -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-3">Resumen del Período</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-primary-600">{{ $this->getTotalHoursForPeriod() }}</div>
                            <div class="text-sm text-gray-500">Horas Totales</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">₡{{ number_format($this->getTotalCostForPeriod(), 2) }}</div>
                            <div class="text-sm text-gray-500">Costo Total</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $this->getProjectEmployees()->count() }}</div>
                            <div class="text-sm text-gray-500">Empleados</div>
                        </div>
                </div>
            </div>
            </x-filament::section>
        @else
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-4">
                        <i class="heroicon-o-folder-open text-6xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Selecciona un Proyecto</h3>
                    <p class="text-gray-500">Elige un proyecto del menú desplegable para gestionar sus horas.</p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

<x-filament::modal id="employee-selection-modal" width="2xl" :key="$modalRefreshKey">
    <x-slot name="heading">
        Seleccionar Empleados
    </x-slot>
    
    <x-slot name="description">
        Selecciona los empleados que deseas agregar al proyecto
    </x-slot>
    
    <!-- Search Input -->
    <div class="mb-4">
        <x-filament::input.wrapper>
            <x-filament::input
                type="text"
                placeholder="Buscar empleados..."
                wire:model.live.debounce.300ms="employeeSearch"
            />
        </x-filament::input.wrapper>
    </div>
    
    <!-- Employee List -->
    <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
        @foreach($this->getFilteredEmployees() as $employee)
            <div 
                class="flex px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors"
                wire:click="toggleEmployeeSelection({{ $employee->id }})"
            >
                <div class="flex items-center mr-8">
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
                <div class="flex items-center">
                    <div class="text-sm font-medium text-gray-900">{{ $employee->name }}</div>
                </div>
            </div>
        @endforeach
    </div>
    
    <x-slot name="footerActions">
        <x-filament::button
            wire:click="addEmployeesToProject"
            color="primary"
            :disabled="empty($selectedEmployeesToAdd)"
        >
            Agregar {{ count($selectedEmployeesToAdd) > 0 ? '(' . count($selectedEmployeesToAdd) . ')' : '' }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>

<!-- Employee Removal Confirmation Modal -->
<x-filament::modal id="remove-employee-confirmation-modal" width="md">
    <x-slot name="heading">
        <div class="flex items-center">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 mr-2" />
            Remover Empleado del Proyecto
        </div>
    </x-slot>
    
    <x-slot name="description">
        <div class="space-y-3">
            <p class="text-gray-700">
                ¿Estás seguro de que deseas remover a <strong>{{ $this->getEmployeeToRemoveName() }}</strong> del proyecto?
            </p>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                <div class="flex">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 mr-2 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-red-700">
                        <p class="font-medium mb-1">Esta acción eliminará:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>El empleado del proyecto</li>
                            <li>Todos sus horas registradas</li>
                        </ul>
                        <p class="font-medium mt-2">Esta acción no se puede deshacer.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
    
    <x-slot name="footerActions">
        <x-filament::button
            wire:click="confirmRemoveEmployee({{ $employeeToRemove }})"
            color="danger"
            icon="heroicon-o-trash"
        >
            Sí, Remover
        </x-filament::button>
        
        <x-filament::button
            wire:click="$dispatch('close-modal', { id: 'remove-employee-confirmation-modal' })"
            color="gray"
        >
            Cancelar
        </x-filament::button>
    </x-slot>
</x-filament::modal>
</div>