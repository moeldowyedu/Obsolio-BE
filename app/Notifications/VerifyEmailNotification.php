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
            Carbon::now()->addHours(24), // Expires in 24 hours
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Build the mail representation of the notification.
     */
    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // 1. Generate the URL (Server-side signed route)
        $verifyUrl = $this->verificationUrl($notifiable);

        // 2. Transform into Frontend URL
        $frontendUrl = \Illuminate\Support\Facades\Config::get('app.frontend_url', 'https://obsolio.com');
        $query = parse_url($verifyUrl, PHP_URL_QUERY);

        // Final Link: https://obsolio.com/verify-email/{id}/{hash}?signature=...
        $actionUrl = "{$frontendUrl}/verify-email/{$notifiable->getKey()}/" . sha1($notifiable->getEmailForVerification()) . "?{$query}";

        return (new MailMessage)
            ->subject('Activate Your OBSOLIO Workspace')
            ->view('emails.verify-email', ['actionUrl' => $actionUrl]);
    }
}
