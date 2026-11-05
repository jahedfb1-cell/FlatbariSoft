<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Tells whoever entered an expense that an approver turned it down, and why. */
class ExpenseRejectedNotification extends Notification
{
    public function __construct(private readonly string $title, private readonly string $body) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => 'EXPENSE_REJECTED', 'title' => $this->title, 'body' => $this->body, 'link' => '/expenses'];
    }
}
