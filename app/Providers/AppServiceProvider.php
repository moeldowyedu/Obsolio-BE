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
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Override the verification URL generation
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, $url) {

            // Generate the BACKEND API signed verification URL
            $currentRoot = \Illuminate\Support\Facades\URL::formatRoot('', '');
            $isProduction = app()->environment('production');

            // ⚠️ CRITICAL Fix: Force API domain for signature generation in production
            // This ensures the signature matches the REQUEST URL (api.obsolio.com) 
            // even if APP_URL is set to obsolio.com in .env
            if ($isProduction) {
                \Illuminate\Support\Facades\URL::forceRootUrl('https://api.obsolio.com');
            }

            try {
                $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'verification.verify',
                    \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
                    [
                        'id' => $notifiable->getKey(),
                        'hash' => sha1($notifiable->getEmailForVerification()),
                    ]
                );
            } finally {
                // Restore original root to avoid side effects
                if ($isProduction) {
                    \Illuminate\Support\Facades\URL::forceRootUrl($currentRoot);
                }
            }

            // The frontend will receive this link and redirect/call the API
            // The link points directly to the API endpoint with valid signature
            $frontendUrl = \Illuminate\Support\Facades\Config::get('app.frontend_url', 'https://obsolio.com');

            // Extract query parameters from the signed URL
            $query = parse_url($verifyUrl, PHP_URL_QUERY);

            // Create frontend URL that includes the API verification link as parameter
            // Frontend will use this to call the actual API endpoint
            $actionUrl = "{$frontendUrl}/verify-email/{$notifiable->getKey()}/" . sha1($notifiable->getEmailForVerification()) . "?{$query}";

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Activate Your OBSOLIO Workspace')
                ->view('emails.verify-email', ['actionUrl' => $actionUrl]);
        });
    }
}
