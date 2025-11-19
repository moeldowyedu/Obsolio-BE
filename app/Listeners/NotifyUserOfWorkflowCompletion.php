<?php

namespace App\Listeners;

use App\Events\WorkflowCompleted;
use App\Jobs\SendNotificationJob;
use App\Jobs\TriggerWebhookJob;
use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserOfWorkflowCompletion implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(WorkflowCompleted $event): void
    {
        $execution = $event->execution;

        // Notify user
        if ($execution->triggered_by_user_id) {
            SendNotificationJob::dispatch(
                $execution->triggeredBy,
                'email',
                [
                    'subject' => 'Workflow Completed',
                    'content' => "Your workflow '{$execution->workflow->name}' has completed successfully.",
                ]
            );
        }

        // Trigger webhooks
        $webhooks = Webhook::where('tenant_id', $execution->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            TriggerWebhookJob::dispatch(
                $webhook,
                'workflow.completed',
                [
                    'execution_id' => $execution->id,
                    'workflow_id' => $execution->workflow_id,
                    'status' => $execution->status,
                ]
            );
        }
    }
}
