<?php

namespace App\Filament\Pages;

use App\Models\GlobalConfig as GlobalConfigModel;
use App\Policies\GlobalConfigPolicy;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;

class GlobalConfig extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configuraciones Globales';
    protected static ?string $title = 'Configuraciones Globales';
    protected static ?string $navigationGroup = 'Configuraciones';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.global-config';

    public ?array $data = [];

    /**
     * Check if the current user can access this page
     * Uses Filament Shield policy to verify view permissions
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_GlobalConfig') ?? false;
    }

    public function mount(): void
    {
        $this->loadConfigData();
        
        // Fill the form with the loaded data
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuraciones de Nómina')
                    ->description('Configuraciones para el cálculo de salarios')
                    ->schema([
                        TextInput::make('night_work_bonus')
                            ->label('Bono Trabajo Nocturno')
                            ->prefix('₡')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(100)
                            ->rules(['required', 'numeric', 'min:0'])
                            ->helperText('Monto por día de trabajo nocturno'),
                    ])
                    ->compact()
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Configuración')
                ->action('save')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->size('md'),
            
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            
            // Validate the form data
            $this->validate();
            
            // Save configuration to database
            $this->saveConfigData($data);
            
            // Reload the form data to reflect changes
            $this->loadConfigData();
            $this->form->fill($this->data);
            
            Notification::make()
                ->title('Configuración Guardada')
                ->body('Las configuraciones globales han sido guardadas exitosamente.')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al Guardar')
                ->body('Hubo un error al guardar la configuración: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }


    private function loadConfigData(): void
    {
        // Load configuration from database
        $this->data = GlobalConfigModel::getAllAsArray();
        
        // Only set values that exist in the database
        // If no values exist, the form will be empty
    }

    private function saveConfigData(array $data): void
    {
        // Save each configuration to database
        foreach ($data as $key => $value) {
            // Save the configuration (this will automatically log the activity)
            GlobalConfigModel::setValue(
                $key,
                $value,
                'integer', // For night_work_bonus
                $this->getConfigDescription($key)
            );
        }
    }

    private function getConfigDescription(string $key): string
    {
        $descriptions = [
            'night_work_bonus' => 'Monto en colones por día de trabajo nocturno',
        ];

        return $descriptions[$key] ?? '';
    }

    /**
     * Get the night work bonus amount from global configuration
     * Returns the configured amount or null if not set
     */
    public static function getNightWorkBonus(): ?int
    {
        return GlobalConfigModel::getValue('night_work_bonus');
    }
}
