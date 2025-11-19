<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\AgentExecution;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExecuteAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AgentExecution $execution,
        public Agent $agent,
        public array $inputData,
        public ?array $context = null,
        public ?string $userId = null
    ) {
        // Set queue based on priority
        $this->onQueue($this->determineQueue());
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            // Update execution status
            $this->execution->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // TODO: Replace with actual AI engine call
            $response = $this->callAIEngine();

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Update execution with results
            $this->execution->update([
                'status' => 'completed',
                'output_data' => $response['output'],
                'execution_time_ms' => $executionTime,
                'tokens_used' => $response['tokens_used'] ?? 0,
                'cost' => $response['cost'] ?? 0,
                'completed_at' => now(),
            ]);

            // Dispatch event for completion
            event(new \App\Events\AgentExecutionCompleted($this->execution));

            activity()
                ->performedOn($this->execution)
                ->causedBy($this->userId)
                ->withProperties(['status' => 'completed'])
                ->log('Agent execution completed');

        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Agent execution failed', [
                'execution_id' => $this->execution->id,
                'agent_id' => $this->agent->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
                'completed_at' => now(),
            ]);

            // Dispatch event for failure
            event(new \App\Events\AgentExecutionFailed($this->execution, $e));

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->execution->update([
            'status' => 'failed',
            'error_message' => 'Maximum retry attempts exceeded: ' . $exception->getMessage(),
        ]);

        Log::critical('Agent execution permanently failed', [
            'execution_id' => $this->execution->id,
            'agent_id' => $this->agent->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Call AI engine (placeholder for actual implementation)
     */
    private function callAIEngine(): array
    {
        // TODO: Implement actual AI engine integration
        // This is where you'd call OpenAI, Anthropic, etc.

        $config = $this->agent->config;

        // Simulate API call
        sleep(1); // Remove in production

        return [
            'output' => [
                'response' => 'AI response based on input',
                'confidence' => 0.95,
            ],
            'tokens_used' => rand(100, 1000),
            'cost' => rand(1, 100) / 10000,
        ];
    }

    /**
     * Determine which queue to use based on agent priority
     */
    private function determineQueue(): string
    {
        // High-priority agents go to faster queue
        if (isset($this->agent->config['priority']) && $this->agent->config['priority'] === 'high') {
            return 'high';
        }

        return 'default';
    }

    /**
     * Get tags for monitoring
     */
    public function tags(): array
    {
        return [
            'agent:' . $this->agent->id,
            'tenant:' . $this->agent->tenant_id,
            'type:agent-execution',
        ];
    }
}
