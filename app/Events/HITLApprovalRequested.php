<?php

namespace App\Events;

use App\Models\HITLApproval;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HITLApprovalRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public HITLApproval $approval
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->approval->tenant_id),
            new PrivateChannel('user.' . $this->approval->assigned_to_user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'hitl.approval.requested';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'approval_id' => $this->approval->id,
            'agent_id' => $this->approval->agent_id,
            'priority' => $this->approval->priority,
            'assigned_to_user_id' => $this->approval->assigned_to_user_id,
            'expires_at' => $this->approval->expires_at?->toIso8601String(),
        ];
    }
}
