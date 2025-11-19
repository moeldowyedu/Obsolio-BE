<?php

namespace App\Listeners;

use App\Events\HITLApprovalRequested;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendHITLApprovalNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(HITLApprovalRequested $event): void
    {
        $approval = $event->approval;

        // Notify assigned user
        SendNotificationJob::dispatch(
            $approval->assignedTo,
            'email',
            [
                'subject' => 'HITL Approval Required',
                'content' => "An AI decision requires your approval. Priority: {$approval->priority}. Agent: {$approval->agent->name}",
            ]
        );

        // If high priority or urgent, also send push notification
        if (in_array($approval->priority, ['high', 'urgent'])) {
            SendNotificationJob::dispatch(
                $approval->assignedTo,
                'push',
                [
                    'title' => 'Urgent: Approval Required',
                    'body' => "AI decision needs immediate review",
                ]
            );
        }
    }
}
