<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $type,
        public array $data
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            match ($this->type) {
                'email' => $this->sendEmail(),
                'push' => $this->sendPushNotification(),
                'sms' => $this->sendSMS(),
                default => throw new \Exception("Unknown notification type: {$this->type}"),
            };

            Log::info('Notification sent successfully', [
                'user_id' => $this->user->id,
                'type' => $this->type,
            ]);

        } catch (\Exception $e) {
            Log::error('Notification failed', [
                'user_id' => $this->user->id,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail(): void
    {
        $subject = $this->data['subject'] ?? 'Notification';
        $content = $this->data['content'] ?? '';

        // TODO: Use Mailable classes for different email types
        Mail::raw($content, function ($message) use ($subject) {
            $message->to($this->user->email)
                ->subject($subject);
        });
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(): void
    {
        // TODO: Implement push notification (Firebase, OneSignal, etc.)
        Log::info('Push notification would be sent here');
    }

    /**
     * Send SMS
     */
    private function sendSMS(): void
    {
        // TODO: Implement SMS (Twilio, Vonage, etc.)
        Log::info('SMS would be sent here');
    }

    /**
     * Get tags for monitoring
     */
    public function tags(): array
    {
        return [
            'user:' . $this->user->id,
            'type:notification',
            'notification-type:' . $this->type,
        ];
    }
}
