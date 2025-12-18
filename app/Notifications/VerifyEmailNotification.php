<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $actionUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Activate Your OBSOLIO Workspace')
            ->view('emails.verify-email', ['actionUrl' => $actionUrl]);
    }
}