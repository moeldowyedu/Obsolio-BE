<?php

namespace App\Events;

use App\Models\WorkflowExecution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public WorkflowExecution $execution
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->execution->tenant_id),
            new PrivateChannel('workflow.' . $this->execution->workflow_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'workflow_id' => $this->execution->workflow_id,
            'status' => $this->execution->status,
            'completed_at' => $this->execution->completed_at?->toIso8601String(),
        ];
    }
}
