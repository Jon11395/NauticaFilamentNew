<?php

namespace App\Filament\Pages;

use App\Models\GlobalConfig as GlobalConfigModel;
use App\Policies\GlobalConfigPolicy;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
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
                Tabs::make('SettingsTabs')
                    ->tabs([
                        Tabs\Tab::make('nomina')
                            ->label('Nómina')
                            ->icon('heroicon-o-currency-dollar')
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
                            ]),

                        Tabs\Tab::make('gmail')
                            ->label('Gmail')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('Configuración de Gmail')
                                    ->description('Configuración de OAuth para conexión con Gmail API')
                                    ->schema([
                                        ViewField::make('gmail_instructions')
                                            ->view('filament.pages.gmail-instructions')
                                            ->columnSpan('full'),

                                        TextInput::make('gmail_client_id')
                                            ->label('Client ID de Gmail')
                                            ->helperText('Encontrado en: Google Cloud Console > APIs y Servicios > Credenciales')
                                            ->placeholder('Ingresa tu Client ID de OAuth de Gmail desde Google Cloud Console')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('gmail_client_secret')
                                            ->label('Client Secret de Gmail')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Mantén esto seguro y nunca lo compartas públicamente')
                                            ->placeholder('Ingresa tu Client Secret de OAuth de Gmail')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('gmail_refresh_token')
                                            ->label('Refresh Token de Gmail')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Obtén esto desde Google OAuth Playground usando los pasos anteriores')
                                            ->placeholder('Ingresa tu Refresh Token de Gmail')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        TextInput::make('gmail_user_email')
                                            ->label('Correo Electrónico de Usuario de Gmail')
                                            ->email()
                                            ->helperText('La dirección de correo de la cuenta de Gmail que deseas acceder')
                                            ->placeholder('Ingresa la dirección de correo de Gmail para conectar')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        ViewField::make('test_connection')
                                            ->view('filament.pages.gmail-test-button')
                                            ->columnSpan('full'),
                                    ])
                                    ->columns(2)
                                    ->compact(),
                            ]),
                    ]),
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
        // Define the type for each field
        $fieldTypes = [
            'night_work_bonus' => 'integer',
            'gmail_client_id' => 'string',
            'gmail_client_secret' => 'string',
            'gmail_refresh_token' => 'string',
            'gmail_user_email' => 'string',
        ];

        // Fields to exclude from saving (ViewField components like instructions and buttons)
        $excludedFields = ['gmail_instructions', 'test_connection'];

        // Save each configuration to database
        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, $excludedFields)) {
                continue;
            }

            // Skip if value is null or empty and not explicitly set
            if ($value === null || $value === '') {
                continue;
            }

            // Save the configuration (this will automatically log the activity)
            GlobalConfigModel::setValue(
                $key,
                $value,
                $fieldTypes[$key] ?? 'string',
                $this->getConfigDescription($key)
            );
        }
    }

    private function getConfigDescription(string $key): string
    {
        $descriptions = [
            'night_work_bonus' => 'Monto en colones por día de trabajo nocturno',
            'gmail_client_id' => 'Client ID de OAuth de Gmail desde Google Cloud Console',
            'gmail_client_secret' => 'Client Secret de OAuth de Gmail',
            'gmail_refresh_token' => 'Refresh Token de Gmail para acceso API',
            'gmail_user_email' => 'Correo electrónico de cuenta de Gmail',
        ];

        return $descriptions[$key] ?? '';
    }

    public function testGmailConnection(): void
    {
        try {
            $data = $this->form->getState();

            // Check if credentials are provided
            if (empty($data['gmail_client_id']) || empty($data['gmail_client_secret'])) {
                Notification::make()
                    ->title('Faltan credenciales')
                    ->body('Por favor, proporcione su Client ID y Client Secret de Gmail primero.')
                    ->warning()
                    ->send();
                return;
            }

            if (empty($data['gmail_refresh_token'])) {
                Notification::make()
                    ->title('Falta el Refresh Token')
                    ->body('Por favor, proporcione su Refresh Token de Gmail primero.')
                    ->warning()
                    ->send();
                return;
            }

            // Temporarily save credentials to test connection
            GlobalConfigModel::setValue('gmail_client_id', $data['gmail_client_id'] ?? '', 'string', 'Client ID de Gmail');
            GlobalConfigModel::setValue('gmail_client_secret', $data['gmail_client_secret'] ?? '', 'string', 'Client Secret de Gmail');
            GlobalConfigModel::setValue('gmail_refresh_token', $data['gmail_refresh_token'] ?? '', 'string', 'Refresh Token de Gmail');
            GlobalConfigModel::setValue('gmail_user_email', $data['gmail_user_email'] ?? '', 'string', 'Email de usuario de Gmail');

            // Use the GmailService to test the connection
            if (class_exists('\App\Services\GmailService')) {
                $gmailService = new \App\Services\GmailService();

                if ($gmailService->initialize()) {
                    $emails = $gmailService->getUnreadEmails(20);
                    
                    Notification::make()
                        ->title('Conexión exitosa! ✓')
                        ->body('La conexión de Gmail está funcionando correctamente! Se encontraron ' . count($emails) . ' correos electrónicos no leídos.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Conexión fallida')
                        ->body('No se pudo inicializar el servicio de Gmail. Por favor, verifique sus credenciales y pruebe nuevamente.')
                        ->danger()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Servicio no encontrado')
                    ->body('La clase GmailService no fue encontrada. Por favor, asegúrese de que exista.')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error de conexión')
                ->body('Ocurrió un error al probar la conexión: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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
