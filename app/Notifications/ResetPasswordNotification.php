<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('AidatCep şifre sıfırlama')
            ->line('Hesabınız için bir şifre sıfırlama isteği aldık.')
            ->action('Şifreyi sıfırla', $url)
            ->line('Bu bağlantı '.$minutes.' dakika geçerlidir.')
            ->line('İsteği siz yapmadıysanız bu e-postayı yok sayın.');
    }
}
