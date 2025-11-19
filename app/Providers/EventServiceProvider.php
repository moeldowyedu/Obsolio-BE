<?php

namespace App\Providers;

use App\Events\AgentExecutionCompleted;
use App\Events\AgentExecutionFailed;
use App\Events\HITLApprovalRequested;
use App\Events\WorkflowCompleted;
use App\Events\WorkflowFailed;
use App\Listeners\AlertOnExecutionFailure;
use App\Listeners\NotifyUserOfExecutionCompletion;
use App\Listeners\NotifyUserOfWorkflowCompletion;
use App\Listeners\SendHITLApprovalNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AgentExecutionCompleted::class => [
            NotifyUserOfExecutionCompletion::class,
        ],
        AgentExecutionFailed::class => [
            AlertOnExecutionFailure::class,
        ],
        WorkflowCompleted::class => [
            NotifyUserOfWorkflowCompletion::class,
        ],
        WorkflowFailed::class => [
            AlertOnExecutionFailure::class, // Reuse same listener
        ],
        HITLApprovalRequested::class => [
            SendHITLApprovalNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
