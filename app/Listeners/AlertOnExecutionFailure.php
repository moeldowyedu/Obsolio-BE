<?php

namespace App\Listeners;

use App\Events\AgentExecutionFailed;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AlertOnExecutionFailure implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AgentExecutionFailed $event): void
    {
        $execution = $event->execution;
        $exception = $event->exception;

        // Log the failure
        Log::error('Agent execution failed - alerting user', [
            'execution_id' => $execution->id,
            'agent_id' => $execution->agent_id,
            'error' => $exception->getMessage(),
        ]);

        // Notify the user
        if ($execution->triggered_by_user_id) {
            SendNotificationJob::dispatch(
                $execution->triggeredBy,
                'email',
                [
                    'subject' => 'Agent Execution Failed',
                    'content' => "Your agent '{$execution->agent->name}' execution failed: {$execution->error_message}",
                ]
            );
        }

        // If it's a job flow, notify the supervisor
        if ($execution->job_flow_id && $execution->jobFlow->hitl_supervisor_id) {
            SendNotificationJob::dispatch(
                $execution->jobFlow->hitlSupervisor,
                'email',
                [
                    'subject' => 'Job Flow Execution Failed',
                    'content' => "Job flow '{$execution->jobFlow->job_title}' execution failed and requires attention.",
                ]
            );
        }
    }
}
