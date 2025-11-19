<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minutes for complex workflows

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WorkflowExecution $execution,
        public Workflow $workflow,
        public array $inputData,
        public ?string $userId = null
    ) {
        $this->onQueue('workflows');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->execution->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            $workflowDefinition = $this->workflow->workflow_definition;
            $nodes = $workflowDefinition['nodes'] ?? [];
            $edges = $workflowDefinition['edges'] ?? [];

            $executionLog = [];
            $currentData = $this->inputData;
            $currentStep = 0;

            // Process workflow nodes in order
            foreach ($nodes as $node) {
                $currentStep++;
                $this->execution->update(['current_step' => $currentStep]);

                $executionLog[] = [
                    'step' => $currentStep,
                    'node_id' => $node['id'],
                    'node_type' => $node['type'],
                    'timestamp' => now()->toIso8601String(),
                    'status' => 'processing',
                ];

                try {
                    // Process node based on type
                    $result = $this->processNode($node, $currentData);
                    $currentData = array_merge($currentData, $result);

                    $executionLog[count($executionLog) - 1]['status'] = 'completed';
                    $executionLog[count($executionLog) - 1]['output'] = $result;

                } catch (\Exception $e) {
                    $executionLog[count($executionLog) - 1]['status'] = 'failed';
                    $executionLog[count($executionLog) - 1]['error'] = $e->getMessage();
                    throw $e;
                }
            }

            $this->execution->update([
                'status' => 'completed',
                'output_data' => $currentData,
                'execution_log' => $executionLog,
                'completed_at' => now(),
            ]);

            event(new \App\Events\WorkflowCompleted($this->execution));

            activity()
                ->performedOn($this->execution)
                ->causedBy($this->userId)
                ->log('Workflow execution completed');

        } catch (\Exception $e) {
            Log::error('Workflow execution failed', [
                'execution_id' => $this->execution->id,
                'workflow_id' => $this->workflow->id,
                'error' => $e->getMessage(),
            ]);

            $this->execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            event(new \App\Events\WorkflowFailed($this->execution, $e));

            throw $e;
        }
    }

    /**
     * Process individual workflow node
     */
    private function processNode(array $node, array $data): array
    {
        $nodeType = $node['type'];

        return match ($nodeType) {
            'agent' => $this->processAgentNode($node, $data),
            'condition' => $this->processConditionNode($node, $data),
            'transform' => $this->processTransformNode($node, $data),
            'api_call' => $this->processApiCallNode($node, $data),
            default => throw new \Exception("Unknown node type: {$nodeType}"),
        };
    }

    /**
     * Process agent node
     */
    private function processAgentNode(array $node, array $data): array
    {
        // TODO: Call agent execution
        return ['agent_result' => 'Agent execution result'];
    }

    /**
     * Process condition node
     */
    private function processConditionNode(array $node, array $data): array
    {
        $condition = $node['condition'] ?? '';
        // TODO: Evaluate condition
        return ['condition_met' => true];
    }

    /**
     * Process transform node
     */
    private function processTransformNode(array $node, array $data): array
    {
        // TODO: Apply data transformation
        return $data;
    }

    /**
     * Process API call node
     */
    private function processApiCallNode(array $node, array $data): array
    {
        // TODO: Make external API call
        return ['api_response' => 'API call result'];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Workflow permanently failed', [
            'execution_id' => $this->execution->id,
            'workflow_id' => $this->workflow->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get tags for monitoring
     */
    public function tags(): array
    {
        return [
            'workflow:' . $this->workflow->id,
            'tenant:' . $this->workflow->tenant_id,
            'type:workflow',
        ];
    }
}
