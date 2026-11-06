<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** "Your payment was accepted / turned down" - shown in the app for everyone who lives in, or owns, the flat. */
class PaymentStatusNotification extends Notification
{
    public function __construct(
        private readonly bool $accepted,
        private readonly string $title,
        private readonly string $body,
        private readonly ?int $paymentId = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->accepted ? 'PAYMENT_APPROVED' : 'PAYMENT_REJECTED', 'title' => $this->title, 'body' => $this->body,
            'payment_id' => $this->paymentId, 'link' => '/owner/payments',
        ];
    }
}
