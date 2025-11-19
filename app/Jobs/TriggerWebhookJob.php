<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Webhook $webhook,
        public string $event,
        public array $payload
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->webhook->is_active) {
            Log::info('Webhook inactive, skipping', [
                'webhook_id' => $this->webhook->id,
                'event' => $this->event,
            ]);
            return;
        }

        // Check if webhook handles this event
        if (!in_array($this->event, $this->webhook->events)) {
            return;
        }

        try {
            $headers = array_merge(
                [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $this->event,
                    'X-Webhook-ID' => $this->webhook->id,
                    'X-Webhook-Timestamp' => now()->toIso8601String(),
                ],
                $this->webhook->headers ?? []
            );

            // Add signature if secret exists
            if ($this->webhook->secret) {
                $signature = hash_hmac('sha256', json_encode($this->payload), $this->webhook->secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->post($this->webhook->url, [
                    'event' => $this->event,
                    'data' => $this->payload,
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                $this->webhook->increment('total_calls');
                $this->webhook->update(['last_triggered_at' => now()]);

                Log::info('Webhook triggered successfully', [
                    'webhook_id' => $this->webhook->id,
                    'event' => $this->event,
                    'status' => $response->status(),
                ]);
            } else {
                throw new \Exception("Webhook failed with status {$response->status()}");
            }

        } catch (\Exception $e) {
            $this->webhook->increment('failed_calls');

            Log::error('Webhook trigger failed', [
                'webhook_id' => $this->webhook->id,
                'event' => $this->event,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->webhook->increment('failed_calls');

        Log::critical('Webhook permanently failed', [
            'webhook_id' => $this->webhook->id,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);

        // Optionally disable webhook after multiple failures
        if ($this->webhook->failed_calls >= 10) {
            $this->webhook->update(['is_active' => false]);
            Log::warning('Webhook auto-disabled due to excessive failures', [
                'webhook_id' => $this->webhook->id,
            ]);
        }
    }

    /**
     * Get tags for monitoring
     */
    public function tags(): array
    {
        return [
            'webhook:' . $this->webhook->id,
            'event:' . $this->event,
            'type:webhook',
        ];
    }
}
