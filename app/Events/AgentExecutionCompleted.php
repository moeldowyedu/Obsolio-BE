<?php

namespace App\Events;

use App\Models\AgentExecution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentExecutionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AgentExecution $execution
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
            new PrivateChannel('agent.' . $this->execution->agent_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'agent.execution.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'agent_id' => $this->execution->agent_id,
            'status' => $this->execution->status,
            'execution_time_ms' => $this->execution->execution_time_ms,
            'completed_at' => $this->execution->completed_at?->toIso8601String(),
        ];
    }
}
