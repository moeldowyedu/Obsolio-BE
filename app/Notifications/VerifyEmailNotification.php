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
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        // Get tenant subdomain preference
        $tenant = $notifiable->tenant;
        // Check if tenant exists, otherwise fallback or handle error. 
        // Assuming tenant relation is loaded or available.
        $subdomain = $tenant ? $tenant->subdomain_preference : 'workspace';
        $workspaceUrl = $subdomain . '.obsolio.com';

        return (new MailMessage)
            ->subject('Activate Your OBSOLIO Workspace')
            ->greeting('Welcome to OBSOLIO!')
            ->line("Hi {$notifiable->name},")
            ->line("You're just one step away from accessing your workspace!")
            ->line('')
            ->line("**Your Workspace URL:** {$workspaceUrl}")
            ->line('')
            ->action('Activate My Workspace', $verificationUrl)
            ->line('')
            ->line('⏰ This link will expire in 24 hours.')
            ->line('')
            ->line("If you didn't create an account, you can safely ignore this email.")
            ->salutation("Best regards,\nThe OBSOLIO Team");
    }
}
