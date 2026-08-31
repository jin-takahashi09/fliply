<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
        );

        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('パスワード再設定のお知らせ | Fliply')
            ->view('emails.password-reset', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
            ])
            ->text('emails.password-reset-text', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
            ]);
    }

    protected function resetUrl(object $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
