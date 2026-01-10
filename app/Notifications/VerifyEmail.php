<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $domain = Config::get('tenancy.central_domains')[0] ?? 'obsolio.com';

        // Handle localhost dev environment
        $protocol = 'http://';
        if (!str_contains($domain, 'localhost')) {
            $protocol = 'https://';
        }

        // Use the API domain for verification URLs
        // The verification route is on api.obsolio.com, not tenant subdomains
        $baseUrl = "{$protocol}api.{$domain}";

        // Force the URL generator to use the API domain
        $currentRoot = URL::formatRoot('', '');
        URL::forceRootUrl($baseUrl);

        try {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        } finally {
            URL::forceRootUrl($currentRoot); // Restore
        }

        return $url;
    }
}
