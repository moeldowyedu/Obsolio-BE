<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Override the verification URL generation
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, $url) {

            // 1. Generate the FRONTEND URL
            $frontendUrl = \Illuminate\Support\Facades\Config::get('app.frontend_url', 'https://obsolio.com');

            // We need to pass the ID and Hash to the frontend route
            $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify', // Validated this route exists in api.php
                \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // TRANSFORM THE URL to point to frontend
            $query = parse_url($verifyUrl, PHP_URL_QUERY);

            // Construct new Frontend URL: /verify-email/{id}/{hash}?signature=...
            $newUrl = "{$frontendUrl}/verify-email/{$notifiable->getKey()}/" . sha1($notifiable->getEmailForVerification()) . "?{$query}";

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Activate Your OBSOLIO Workspace')
                ->view('emails.verify-email', ['actionUrl' => $newUrl]);
        });
    }
}
