<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTenantVerifiedNotification extends Notification
{
    use Queueable;

    protected $tenant;
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($tenant, $user)
    {
        $this->tenant = $tenant;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Tenant Verified: ' . $this->tenant->name)
            ->view('emails.admin.new-tenant-verified', [
                'tenantName' => $this->tenant->name,
                'subdomain' => $this->tenant->id, // ID is now the subdomain
                'userEmail' => $this->user->email,
                'type' => $this->tenant->type,
                'verifiedAt' => now()->toDayDateTimeString(),
            ]);
    }
}
