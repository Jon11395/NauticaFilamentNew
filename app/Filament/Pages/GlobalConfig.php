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
use Illuminate\Support\Facades\Mail;

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
                                            ->dehydrated(false)
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

                                        TextInput::make('gmail_from_name')
                                            ->label('Nombre del Remitente')
                                            ->helperText('El nombre que aparecerá como remitente en los emails enviados')
                                            ->placeholder('Ej: Náutica')
                                            ->default('Náutica')
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        Select::make('gmail_sync_interval_minutes')
                                            ->label('Sincronización automática')
                                            ->options([
                                                0 => 'Desactivado',
                                                60 => 'Cada hora',
                                                120 => 'Cada 2 horas',
                                                180 => 'Cada 3 horas',
                                                240 => 'Cada 4 horas',
                                                360 => 'Cada 6 horas',
                                                720 => 'Cada 12 horas',
                                                1440 => 'Cada día',
                                                2880 => 'Cada 2 días',
                                                10080 => 'Cada semana',
                                            ])
                                            ->helperText('Frecuencia con la que se ejecutará automáticamente la sincronización de Gmail (mínimo cada hora)')
                                            ->default(0)
                                            ->columnSpan(1),

                                        ViewField::make('test_connection')
                                            ->view('filament.pages.gmail-test-button')
                                            ->dehydrated(false)
                                            ->columnSpan('full'),

                                        ViewField::make('test_email')
                                            ->label('')
                                            ->view('filament.pages.gmail-test-email-button')
                                            ->dehydrated(false)
                                            ->hidden(false)
                                            ->columnSpan('full'),
                                    ])
                                    ->columns(2)
                                    ->compact(),
                            ]),

                        Tabs\Tab::make('expenses')
                            ->label('Gastos')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Configuración de Adjuntos de Gastos')
                                    ->description('Configuración para la eliminación automática de adjuntos de gastos antiguos')
                                    ->schema([
                                        Select::make('expense_attachment_retention_months')
                                            ->label('Retención de Adjuntos')
                                            ->options([
                                                0 => 'No eliminar (Desactivado)',
                                                3 => '3 meses',
                                                6 => '6 meses',
                                                12 => '1 año',
                                                24 => '2 años',
                                            ])
                                            ->helperText('Los adjuntos de gastos más antiguos que este período serán eliminados automáticamente. Los gastos en sí no se eliminarán, solo sus archivos adjuntos. Selecciona "No eliminar" para desactivar la eliminación automática.')
                                            ->default(12)
                                            ->columnSpan(1),
                                    ])
                                    ->compact()
                                    ->columns(1),
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
            'gmail_from_name' => 'string',
            'gmail_sync_interval_minutes' => 'integer',
            'expense_attachment_retention_months' => 'integer',
        ];

        // Fields to exclude from saving (ViewField components like instructions and buttons)
        $excludedFields = ['gmail_instructions', 'test_connection', 'test_email'];

        // Save each configuration to database
        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, $excludedFields)) {
                continue;
            }

            // If value is null or empty, delete the configuration
            if ($value === null || $value === '') {
                GlobalConfigModel::where('key', $key)->delete();
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
            'gmail_sync_interval_minutes' => 'Frecuencia (en minutos) para ejecutar la sincronización automática de Gmail',
            'expense_attachment_retention_months' => 'Período de retención (en meses) para adjuntos de gastos antes de eliminación automática',
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

                try {
                    if (!$gmailService->initialize()) {
                        Notification::make()
                            ->title('Conexión fallida')
                            ->body('No se pudo inicializar el servicio de Gmail. Por favor, verifique sus credenciales y pruebe nuevamente.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Try to get today's emails to verify the connection
                    $emails = $gmailService->getTodayEmails(20);
                    
                    Notification::make()
                        ->title('Conexión exitosa! ✓')
                        ->body('La conexión de Gmail está funcionando correctamente! Se encontraron ' . count($emails) . ' correos electrónicos recibidos hoy.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error de conexión')
                        ->body('No se pudo conectar con Gmail: ' . $e->getMessage())
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
     * Send a test email to verify email configuration
     */
    public function sendTestEmail(): void
    {
        try {
            $data = $this->form->getState();

            // Check if Gmail email is configured
            $testEmail = $data['gmail_user_email'] ?? GlobalConfigModel::getValue('gmail_user_email');
            
            if (empty($testEmail)) {
                Notification::make()
                    ->title('Email no configurado')
                    ->body('Por favor, configure el correo electrónico de Gmail primero.')
                    ->warning()
                    ->send();
                return;
            }

            // Temporarily save credentials if they're in the form
            if (!empty($data['gmail_client_id']) && !empty($data['gmail_client_secret']) && !empty($data['gmail_refresh_token'])) {
                GlobalConfigModel::setValue('gmail_client_id', $data['gmail_client_id'], 'string', 'Client ID de Gmail');
                GlobalConfigModel::setValue('gmail_client_secret', $data['gmail_client_secret'], 'string', 'Client Secret de Gmail');
                GlobalConfigModel::setValue('gmail_refresh_token', $data['gmail_refresh_token'], 'string', 'Refresh Token de Gmail');
                GlobalConfigModel::setValue('gmail_user_email', $data['gmail_user_email'] ?? $testEmail, 'string', 'Email de usuario de Gmail');
                
                // Save from name if provided
                if (!empty($data['gmail_from_name'])) {
                    GlobalConfigModel::setValue('gmail_from_name', $data['gmail_from_name'], 'string', 'Nombre del Remitente de Gmail');
                }
            }

            // Force synchronous sending for testing
            $originalQueue = config('queue.default');
            config(['queue.default' => 'sync']);

            // Ensure the mail from address is set correctly
            $fromEmail = $testEmail;
            // Get from name from GlobalConfig, fallback to config or default
            $fromName = GlobalConfigModel::getValue('gmail_from_name') 
                ?? config('mail.from.name', 'Náutica');
            
            // Ensure from name is not "Laravel" or empty which causes validation errors
            if ($fromName === 'Laravel' || empty($fromName)) {
                $fromName = 'Náutica';
            }
            
            // Validate email address
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                Notification::make()
                    ->title('Email Inválido')
                    ->body('La dirección de correo electrónico configurada no es válida: ' . $fromEmail)
                    ->danger()
                    ->send();
                return;
            }
            
            // Temporarily set the mail from address
            $originalFromAddress = config('mail.from.address');
            $originalFromName = config('mail.from.name');
            config(['mail.from.address' => $fromEmail]);
            config(['mail.from.name' => $fromName]);

            // Send test email - explicitly set from address to avoid validation issues
            // Use mailer() to get a fresh instance with updated config
            try {
                Mail::mailer('gmail-api')->raw('Este es un correo de prueba enviado desde el sistema de configuración global. Si recibes este correo, significa que la configuración de Gmail OAuth está funcionando correctamente.', function ($message) use ($testEmail, $fromEmail, $fromName) {
                    // Clear any existing from addresses first to avoid validation issues
                    $message->getHeaders()->remove('from');
                    
                    // Ensure from address is set before any other operations
                    // Double-check that $fromEmail is actually an email address
                    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException('Invalid from email address: ' . $fromEmail);
                    }
                    
                    // Set from address - email first, name second
                    $message->from($fromEmail, $fromName);
                    $message->to($testEmail);
                    $message->subject('Email de Prueba - Configuración Gmail OAuth');
                });
            } finally {
                // Restore original config
                config(['mail.from.address' => $originalFromAddress]);
                config(['mail.from.name' => $originalFromName]);
            }

            // Restore original queue setting
            config(['queue.default' => $originalQueue]);

            Notification::make()
                ->title('Email de Prueba Enviado')
                ->body('El email de prueba ha sido enviado exitosamente a: ' . $testEmail . '. Verifica tu bandeja de entrada y carpeta de spam.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al Enviar Email')
                ->body('No se pudo enviar el email de prueba: ' . $e->getMessage())
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
