<?php

namespace App\Listeners;

use App\Events\AgentExecutionCompleted;
use App\Jobs\SendNotificationJob;
use App\Jobs\TriggerWebhookJob;
use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserOfExecutionCompletion implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AgentExecutionCompleted $event): void
    {
        $execution = $event->execution;

        // Send notification to user who triggered the execution
        if ($execution->triggered_by_user_id) {
            SendNotificationJob::dispatch(
                $execution->triggeredBy,
                'email',
                [
                    'subject' => 'Agent Execution Completed',
                    'content' => "Your agent '{$execution->agent->name}' has completed execution successfully.",
                ]
            );
        }

        // Trigger webhooks for this event
        $webhooks = Webhook::where('tenant_id', $execution->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            TriggerWebhookJob::dispatch(
                $webhook,
                'agent.executed',
                [
                    'execution_id' => $execution->id,
                    'agent_id' => $execution->agent_id,
                    'status' => $execution->status,
                    'execution_time_ms' => $execution->execution_time_ms,
                ]
            );
        }
    }
}
