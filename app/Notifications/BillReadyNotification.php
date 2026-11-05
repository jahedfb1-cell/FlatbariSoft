<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** "Your bill is ready" - shown in the app for everyone who lives in, or owns, the flat. */
class BillReadyNotification extends Notification
{
    public function __construct(
        private readonly int $billId,
        private readonly string $title,
        private readonly string $body,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => 'BILL', 'title' => $this->title, 'body' => $this->body, 'bill_id' => $this->billId, 'link' => '/owner/bills'];
    }
}
