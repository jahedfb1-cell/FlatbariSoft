<?php

namespace App\Contracts;

/**
 * Driver-pattern boundary for SMS delivery (Phase 3.1). The app must work
 * end-to-end with zero real gateway wired in — NullSmsGateway is bound by
 * default — so a future real gateway is a second class implementing this
 * interface plus one config change, never a rewrite of SmsService.
 */
interface SmsGatewayInterface
{
    /**
     * @return array{success: bool, gateway: string, cost: float}
     */
    public function send(string $mobile, string $message): array;
}
