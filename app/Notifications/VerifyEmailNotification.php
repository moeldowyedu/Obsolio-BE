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
        // Generate the API verification URL with signature
        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Parse the API URL to extract query parameters
        $urlParts = parse_url($apiUrl);
        parse_str($urlParts['query'] ?? '', $queryParams);

        // Build frontend URL with all necessary parameters
        $frontendUrl = config('app.frontend_url', config('app.url'));

        // Create frontend verification URL
        return $frontendUrl . '/verify-email?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $queryParams['expires'] ?? '',
            'signature' => $queryParams['signature'] ?? '',
        ]);
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