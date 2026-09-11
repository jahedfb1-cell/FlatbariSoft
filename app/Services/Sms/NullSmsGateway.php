<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * The only gateway wired in until a real one (Phase 3.1+ ops decision) is
 * bought and configured. Never actually sends anything — logs the message
 * so the flow is fully testable/demoable without an SMS bill, and every
 * sms_logs row it touches stays QUEUED_PENDING_GATEWAY (never SENT/FAILED,
 * since nothing was genuinely attempted).
 */
class NullSmsGateway implements SmsGatewayInterface
{
    public function send(string $mobile, string $message): array
    {
        Log::info("[NullSmsGateway] Would send to {$mobile}: {$message}");

        return ['success' => false, 'gateway' => 'null', 'cost' => 0];
    }
}
