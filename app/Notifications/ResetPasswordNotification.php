<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        
        // Encode subject properly for email headers to avoid double-encoding issues
        // Use Q encoding (quoted-printable) which is more compatible with Gmail API
        $subjectText = 'Notificación de Restablecimiento de Contraseña';
        $subject = mb_encode_mimeheader($subjectText, 'UTF-8', 'Q', "\r\n");
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line('Estás recibiendo este correo porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.')
            ->action('Restablecer Contraseña', $this->resetUrl($notifiable))
            ->line("Este enlace de restablecimiento de contraseña expirará en {$expireMinutes} minutos.")
            ->line('Si no solicitaste un restablecimiento de contraseña, no se requiere ninguna acción adicional.');
    }

    protected function resetUrl(mixed $notifiable): string
    {
        return Filament::getResetPasswordUrl($this->token, $notifiable);
    }
}

