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
        $tenantId = $notifiable->tenant_id;
        $domain = Config::get('tenancy.central_domains')[0] ?? 'obsolio.com';

        // Handle localhost dev environment
        $protocol = 'http://';
        if (!str_contains($domain, 'localhost')) {
            $protocol = 'https://';
        }

        // Construct the tenant domain base URL
        // e.g., http://tenant1.localhost:8000
        $baseUrl = "{$protocol}{$tenantId}.{$domain}";

        // We create a signed URL manually pointing to the API endpoint
        // The endpoint is: GET /api/v1/auth/email/verify/{id}/{hash}
        // Note: The route name must exist in api.php for this to work perfectly if we used route(),
        // but since we are constructing cross-domain, we build it manually or force the root.

        // To verify the signature, the backend will check the signature against the URL.
        // The signature includes the host. So we MUST include the host in signature generation if we want standard validation.
        // However, standard `temporarySignedRoute` uses the CURRENT request's host.
        // We want to generate a signature valid for the TARGET host.
        // Laravel's URL generator can handle this if we set the root temporarily? No that's hacky.

        // Simpler approach:
        // Use `URL::temporarySignedRoute` but FORCE the domain via the route parameters if the route supports domain.
        // But our route definition is inside a tenant middleware group.

        /*
         * Ideally:
         * 1. Define route 'verification.verify' in api.php
         * 2. Use UrlGenerator to create the signed URL forcing the domain.
         */

        // Let's implement manually using the same logic as Laravel but with our target host

        // Let's implement manually using the same logic as Laravel but with our target host

        // ISSUE: `URL::temporarySignedRoute` will use the CENTRAL domain (localhost:8000) if called from Register (central context).
        // We need it to be `tenant.localhost:8000`.
        // We can replace the host in the generated URL?
        // Method:
        // 1. Generate signed URL (it will be localhost:8000/api/v1/...)
        // 2. Replace `localhost:8000` with `tenant.localhost:8000`
        // 3. BUT the signature depends on the full URL including host! So the signature will become INVALID if we change host after signing.

        // FIX: We must force the URL generator to use the tenant domain.
        // Or we use a custom signature that doesn't check host (less secure).
        // OR better: We rely on the fact that we can pass the "root" to the URL generator?

        // Actually, `URL::forceRootUrl` might work temporarily.

        // Let's try this:
        $currentRoot = URL::formatRoot('', '');
        URL::forceRootUrl($baseUrl); // Switch to https://tenant.obsolio.com

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
