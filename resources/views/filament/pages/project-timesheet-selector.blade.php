<div>
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Selección de Proyecto
            </x-slot>
            
            <x-slot name="description">
                Selecciona un proyecto para gestionar sus horas trabajadas (rango flexible de 1 a 31 días)
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
                            <!-- Simple Date Range Picker -->
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rango de Fechas</label>
                                
                                <!-- Calendar Display -->
                                <div class="fi-input-wrp rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20">
                                    <div class="fi-input-wrp-inner flex items-center">
                                        <input
                                            type="text"
                                            id="dateRangeDisplay"
                                            placeholder="Selecciona un rango de fechas..."
                                            class="fi-input block w-full border-none bg-transparent px-3 py-1.5 text-base text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.400)] dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] dark:disabled:placeholder:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6 cursor-pointer"
                                            style="padding-right: 2.5rem;"
                                            readonly
                                            onclick="toggleCalendar()"
                                        />
                                        <div class="fi-input-wrp-suffix px-3 py-1.5">
                                            <x-heroicon-o-calendar-days class="w-5 h-5 text-gray-400" />
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Calendar Widget -->
                                <div id="calendarWidget" class="hidden absolute z-50 mt-2 bg-white border border-gray-300 rounded-lg shadow-lg p-3 w-full max-w-xs" style="transition: opacity 0.2s ease-in-out;">
                                    <div class="flex justify-between items-center mb-3">
                                        <button type="button" onclick="previousMonth()" style="padding: 2px; border-radius: 4px; background: transparent; border: none; cursor: pointer;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                            <x-heroicon-o-chevron-left class="w-4 h-4" />
                                        </button>
                                        <h3 id="calendarMonth" style="font-size: 14px; font-weight: 600; color: #374151;"></h3>
                                        <button type="button" onclick="nextMonth()" style="padding: 2px; border-radius: 4px; background: transparent; border: none; cursor: pointer;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='transparent'">
                                            <x-heroicon-o-chevron-right class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <!-- Calendar Grid -->
                                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 6px;">
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">D</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">L</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">M</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">X</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">J</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">V</div>
                                        <div style="text-align: center; font-size: 11px; font-weight: 500; color: #6b7280; padding: 4px 0;">S</div>
                                    </div>

                                    <div id="calendarDays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                                        <!-- Calendar days will be generated here -->
                                    </div>
                                    
                                    <!-- Calendar Actions -->
                                    <div style="display: flex; justify-content: center; margin-top: 12px; padding-top: 8px; border-top: 1px solid #e5e7eb;">
                                        <div style="font-size: 11px; color: #6b7280; padding: 4px 0;">
                                            Haz clic en diferentes días para cambiar la selección
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden inputs for Livewire -->
                                <input type="hidden" wire:model.live="startDate" id="startDate" />
                                <input type="hidden" wire:model.live="endDate" id="endDate" />
                                
                                <!-- Date Range Info -->
                                <div class="text-xs text-gray-500 bg-blue-50 px-3 py-2 rounded-lg mt-2">
                                    <div class="flex items-center space-x-1">
                                        <x-heroicon-o-information-circle class="w-3 h-3" />
                                        <span>Haz clic en dos fechas para seleccionar un rango (máximo 31 días)</span>
                                    </div>
                                </div>
                                
                                <!-- Error Message -->
                                <div id="dateRangeError" class="hidden text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg mt-2">
                                    <div class="flex items-center space-x-1">
                                        <x-heroicon-o-exclamation-triangle class="w-3 h-3" />
                                        <span>El rango de fechas debe ser máximo 31 días</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <div class="flex justify-center sm:justify-end mt-2 sm:mt-0">
                                <x-filament::button
                                    wire:click="goToCurrentPeriod"
                                    size="xs"
                                    color="primary"
                                    tooltip="Ir al período actual"
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
                        <div class="bg-gray-50 border-b border-gray-200" style="width: calc(220px + {{ $this->getDaysInPeriod() }} * 65px);">
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
                        <div class="grid gap-0 mb-0 min-w-max" style="grid-template-columns: 220px repeat({{ $this->getDaysInPeriod() }}, 65px);">
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
                            <div class="group grid gap-0 border-b border-gray-200 hover:bg-gray-50 min-w-max" style="grid-template-columns: 220px repeat({{ $this->getDaysInPeriod() }}, 65px);">
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
                                        <button
                                            wire:click="removeEmployeeFromProject({{ $employee->id }})"
                                            class="self-center lg:self-end transition-colors"
                                            style="color: #dc2626;"
                                            title="Remover del proyecto"
                                        >
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
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
                                                    wire:change.debounce.300ms="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', $event.target.value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
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
                                                    wire:change.debounce.300ms="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.value, document.getElementById('night-work-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').checked)"
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
                                                        wire:change="updateTimesheet({{ $employee->id }}, '{{ $currentDate->format('Y-m-d') }}', document.getElementById('regular-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, document.getElementById('extra-hours-{{ $employee->id }}-{{ $currentDate->format('Y-m-d') }}').value, $event.target.checked)"
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
                            <div class="text-center py-16 bg-white border-t border-gray-200" style="margin-top: 64px; margin-bottom: 64px;">
                                <div class="max-w-md mx-auto px-4">
                                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <x-heroicon-o-users class="w-8 h-8 text-blue-600" />
                                    </div>
                                    <h5 class="text-xl font-semibold text-gray-900 mb-3">No hay empleados asignados</h5>
                                    <p class="text-gray-600 mb-8 leading-relaxed">Selecciona empleados de la lista para agregarlos al proyecto y comenzar a registrar sus horas</p>
                                    
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="flex items-center justify-center space-x-2 text-sm text-gray-600">
                                            <x-heroicon-o-light-bulb class="w-4 h-4 text-yellow-500" />
                                            <span>Usa el botón "Agregar Empleados" para agregar trabajadores</span>
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
                                <div class="mt-3 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Regulares:</span>
                                        <span class="font-medium text-green-600">{{ number_format($this->getTotalRegularHoursForPeriod(), 1) }}h</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-500">Extra:</span>
                                        <span class="font-medium text-orange-600">{{ number_format($this->getTotalExtraHoursForPeriod(), 1) }}h</span>
                                    </div>
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
                                        <x-heroicon-o-wallet class="w-6 h-6 text-green-600" />
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
                    <div class="text-sm font-medium text-gray-900 truncate" style="margin-left: 8px;">{{ $employee->name }}</div>
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

<script>
let currentDate = new Date();
let selectedStartDate = null;
let selectedEndDate = null;
let calendarVisible = false;
let preventClose = false;

const months = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

function toggleCalendar() {
    const calendar = document.getElementById('calendarWidget');
    calendarVisible = !calendarVisible;
    
    if (calendarVisible) {
        calendar.classList.remove('hidden');
        generateCalendar();
    } else {
        calendar.classList.add('hidden');
    }
}

function generateCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update month display
    document.getElementById('calendarMonth').textContent = `${months[month]} ${year}`;
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.style.height = '32px';
        calendarDays.appendChild(emptyCell);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.style.cssText = 'height: 28px; display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer; border-radius: 4px;';
        dayElement.textContent = day;
        
        const date = new Date(year, month, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Check if this date would create an invalid range (more than 31 days)
        let isDateDisabled = false;
        if (selectedStartDate && !selectedEndDate) {
            // We have a start date selected, check if this date would exceed 31 days
            const startTime = new Date(selectedStartDate.getFullYear(), selectedStartDate.getMonth(), selectedStartDate.getDate());
            const endTime = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            const daysDiff = Math.round((endTime - startTime) / (1000 * 60 * 60 * 24)) + 1;
            
            // Disable dates that would exceed 31 days
            if (daysDiff > 31) {
                isDateDisabled = true;
            }
            
            // Disable past dates (dates before the selected start date)
            if (date < selectedStartDate) {
                isDateDisabled = true;
            }
        }
        
        if (isDateDisabled) {
            // Disable dates that would create invalid ranges
            dayElement.style.color = '#d1d5db';
            dayElement.style.cursor = 'not-allowed';
            dayElement.style.backgroundColor = 'transparent';
            dayElement.classList.add('disabled');
        } else {
            // Enable valid dates
            dayElement.addEventListener('click', () => selectDate(date));
            dayElement.addEventListener('mouseenter', function() {
                // Check if this is a range date by looking at the current background color
                const currentBg = this.style.backgroundColor;
                
                // Enhanced hover for range dates - make them darker
                if (currentBg === 'rgb(191, 219, 254)' || currentBg === '#bfdbfe') {
                    // This is a range date, make it darker
                    this.style.backgroundColor = '#93c5fd';
                    this.style.color = '#1e3a8a';
                    this.style.fontWeight = '600';
                    this.classList.add('range-hover');
                } else if (!this.classList.contains('selected')) {
                    // Regular hover for non-selected dates (including today)
                    if (!this.classList.contains('today')) {
                        this.style.backgroundColor = '#f3f4f6';
                        this.classList.add('hovering');
                    }
                    
                    // If we have a start date selected, show range preview (works for all dates including today)
                    if (selectedStartDate && !selectedEndDate) {
                        showRangePreview(selectedStartDate, date);
                    }
                }
            });
            dayElement.addEventListener('mouseleave', function() {
                if (this.classList.contains('hovering')) {
                    this.style.backgroundColor = 'transparent';
                    this.classList.remove('hovering');
                }
                
                // Reset range hover effect
                if (this.classList.contains('range-hover')) {
                    this.style.backgroundColor = '#bfdbfe';
                    this.style.color = '#1e40af';
                    this.style.fontWeight = 'normal';
                    this.classList.remove('range-hover');
                }
                
                // Clear range preview when mouse leaves
                if (selectedStartDate && !selectedEndDate) {
                    clearRangePreview();
                }
            });
        }
        
        // Highlight today
        if (date.getTime() === today.getTime()) {
            dayElement.style.backgroundColor = '#fef3c7';
            dayElement.style.color = '#92400e';
            dayElement.style.fontWeight = '700';
            dayElement.style.border = '2px solid #f59e0b';
            dayElement.classList.add('today');
        }

        // Highlight selected dates
        if (selectedStartDate && date.getTime() === selectedStartDate.getTime()) {
            dayElement.style.backgroundColor = '#3b82f6';
            dayElement.style.color = 'white';
            dayElement.style.fontWeight = '600';
            dayElement.classList.add('selected');
        }
        if (selectedEndDate && date.getTime() === selectedEndDate.getTime()) {
            dayElement.style.backgroundColor = '#3b82f6';
            dayElement.style.color = 'white';
            dayElement.style.fontWeight = '600';
            dayElement.classList.add('selected');
        }

        // Highlight range
        if (selectedStartDate && selectedEndDate) {
            if (date >= selectedStartDate && date <= selectedEndDate) {
                if (date.getTime() !== selectedStartDate.getTime() && date.getTime() !== selectedEndDate.getTime()) {
                    dayElement.style.backgroundColor = '#bfdbfe';
                    dayElement.style.color = '#1e40af';
                    dayElement.classList.add('range');
                }
            }
        }
        
        calendarDays.appendChild(dayElement);
    }
}

function selectDate(date) {
    if (!selectedStartDate) {
        // First click: select start date
        selectedStartDate = date;
        selectedEndDate = null;
        preventClose = true; // Prevent calendar from closing
    } else if (!selectedEndDate) {
        // Second click: select end date
        if (date.getTime() === selectedStartDate.getTime()) {
            // Same date clicked, clear selection
            selectedStartDate = null;
            selectedEndDate = null;
            preventClose = false;
        } else {
            // Different date clicked, set as end date
            if (date < selectedStartDate) {
                selectedEndDate = selectedStartDate;
                selectedStartDate = date;
            } else {
                selectedEndDate = date;
            }
            
            // Range validation is now handled at the UI level (disabled dates)
            // No need for error messages since invalid dates are disabled
            
            hideError();
            preventClose = false; // Allow calendar to close after range is complete
            
            // Update display first
            updateDisplay();
            
            // Auto-apply the selection
            applySelection();
            
            // Don't regenerate calendar after complete selection - just close it
            return;
        }
    } else {
        // Third click: start new selection
        selectedStartDate = date;
        selectedEndDate = null;
        preventClose = true; // Prevent calendar from closing
    }
    
    updateDisplay();
    generateCalendar();
}

function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    generateCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    generateCalendar();
}

// clearSelection function removed - users can now click different days to change selection

function applySelection() {
    if (selectedStartDate && selectedEndDate) {
        document.getElementById('startDate').value = formatDate(selectedStartDate);
        document.getElementById('endDate').value = formatDate(selectedEndDate);
        
        // Trigger Livewire update using a more reliable method
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        
        // Dispatch change events to trigger Livewire updates
        startDateInput.dispatchEvent(new Event('input', { bubbles: true }));
        endDateInput.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Close calendar immediately without delay
        toggleCalendar();
    }
}

function updateDisplay() {
    const display = document.getElementById('dateRangeDisplay');
    console.log('updateDisplay called:', {
        selectedStartDate: selectedStartDate ? selectedStartDate.toDateString() : null,
        selectedEndDate: selectedEndDate ? selectedEndDate.toDateString() : null,
        displayElement: display
    });
    
    if (selectedStartDate && selectedEndDate) {
        const formattedRange = `${formatDate(selectedStartDate)} - ${formatDate(selectedEndDate)}`;
        display.value = formattedRange;
        console.log('Setting range:', formattedRange);
    } else if (selectedStartDate) {
        const formattedStart = formatDate(selectedStartDate);
        display.value = formattedStart;
        console.log('Setting start date:', formattedStart);
    } else {
        display.value = '';
        console.log('Clearing display');
    }
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function showError(message) {
    const errorDiv = document.getElementById('dateRangeError');
    errorDiv.querySelector('span').textContent = message;
    errorDiv.classList.remove('hidden');
}

function hideError() {
    document.getElementById('dateRangeError').classList.add('hidden');
}

// Range preview functions
function showRangePreview(startDate, endDate) {
    // Clear any existing preview
    clearRangePreview();
    
    // Determine the actual start and end dates
    const start = startDate < endDate ? startDate : endDate;
    const end = startDate < endDate ? endDate : startDate;
    
    // Get all day elements
    const calendarDays = document.getElementById('calendarDays');
    const dayElements = calendarDays.querySelectorAll('div');
    
    // Highlight the preview range
    dayElements.forEach(dayElement => {
        const dayText = dayElement.textContent;
        if (dayText && !isNaN(dayText)) {
            const day = parseInt(dayText);
            const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
            
            // Check if this date is in the preview range
            if (date >= start && date <= end) {
                // Don't override selected dates or today
                if (!dayElement.classList.contains('selected') && !dayElement.classList.contains('today')) {
                    dayElement.style.backgroundColor = '#e0f2fe';
                    dayElement.style.color = '#0369a1';
                    dayElement.classList.add('preview-range');
                }
            }
        }
    });
}

function clearRangePreview() {
    // Get all day elements
    const calendarDays = document.getElementById('calendarDays');
    const dayElements = calendarDays.querySelectorAll('div');
    
    // Clear preview styling
    dayElements.forEach(dayElement => {
        if (dayElement.classList.contains('preview-range')) {
            dayElement.style.backgroundColor = 'transparent';
            dayElement.style.color = '';
            dayElement.classList.remove('preview-range');
        }
    });
}

// Close calendar when clicking outside
document.addEventListener('click', function(event) {
    const calendar = document.getElementById('calendarWidget');
    const input = document.getElementById('dateRangeDisplay');
    
    if (calendarVisible && !calendar.contains(event.target) && !input.contains(event.target) && !preventClose) {
        toggleCalendar();
    }
});

// Listen for Livewire updates to sync frontend state
document.addEventListener('livewire:updated', function() {
    console.log('Livewire updated event fired');
    
    // Update JavaScript variables when backend changes
    const startDateValue = document.getElementById('startDate').value;
    const endDateValue = document.getElementById('endDate').value;
    
    console.log('Hidden input values:', { startDateValue, endDateValue });
    
    if (startDateValue && endDateValue) {
        selectedStartDate = new Date(startDateValue);
        selectedEndDate = new Date(endDateValue);
        console.log('Updated JavaScript variables:', { selectedStartDate, selectedEndDate });
        updateDisplay();
        generateCalendar();
    }
});

// Function to sync frontend with backend
function syncWithBackend() {
    const startDateValue = document.getElementById('startDate').value;
    const endDateValue = document.getElementById('endDate').value;
    
    console.log('Syncing with backend:', { startDateValue, endDateValue });
    
    if (startDateValue && endDateValue) {
        selectedStartDate = new Date(startDateValue);
        selectedEndDate = new Date(endDateValue);
        updateDisplay();
        generateCalendar();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set initial values if they exist
    const startDateValue = document.getElementById('startDate').value;
    const endDateValue = document.getElementById('endDate').value;
    
    if (startDateValue && endDateValue) {
        selectedStartDate = new Date(startDateValue);
        selectedEndDate = new Date(endDateValue);
        updateDisplay();
    }
});

// Use event delegation on document level to catch all tab events
document.addEventListener('keydown', function(e) {
    // Only handle tab events on timesheet inputs
    if (e.key === 'Tab' && e.target && (e.target.type === 'number' || e.target.type === 'checkbox')) {
        const target = e.target;
        const id = target.id;
        
        // Check if this is a timesheet input
        if (id.includes('regular-hours') || id.includes('extra-hours') || id.includes('night-work')) {
            e.preventDefault();
            e.stopPropagation();
            
            // Determine the input type based on ID
            let inputType = '';
            if (id.includes('regular-hours')) {
                inputType = 'regular-hours';
            } else if (id.includes('extra-hours')) {
                inputType = 'extra-hours';
            } else if (id.includes('night-work')) {
                inputType = 'night-work';
            }
            
            if (inputType) {
                // Get all inputs of the same type from the document
                const allSameTypeInputs = Array.from(document.querySelectorAll('input[type="number"], input[type="checkbox"]')).filter(inp => 
                    (inputType === 'regular-hours' && inp.id.includes('regular-hours')) ||
                    (inputType === 'extra-hours' && inp.id.includes('extra-hours')) ||
                    (inputType === 'night-work' && inp.id.includes('night-work'))
                );
                
                // Find current input index
                const currentIndex = allSameTypeInputs.indexOf(target);
                
                if (e.shiftKey) {
                    // Shift+Tab: Move to previous
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : allSameTypeInputs.length - 1;
                    const prevInput = allSameTypeInputs[prevIndex];
                    prevInput.focus();
                    if (prevInput.type !== 'checkbox') {
                        prevInput.select();
                    }
                } else {
                    // Tab: Move to next
                    const nextIndex = currentIndex < allSameTypeInputs.length - 1 ? currentIndex + 1 : 0;
                    const nextInput = allSameTypeInputs[nextIndex];
                    nextInput.focus();
                    if (nextInput.type !== 'checkbox') {
                        nextInput.select();
                    }
                }
            }
        }
    }
});
</script>