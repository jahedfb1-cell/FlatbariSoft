<?php

namespace App\Jobs;

use App\Contracts\SmsGatewayInterface;
use App\Models\SmsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $smsLogId) {}

    public function handle(SmsGatewayInterface $gateway): void
    {
        $log = SmsLog::find($this->smsLogId);
        if (! $log) {
            return;
        }

        $result = $gateway->send($log->mobile, $log->message);

        // NullSmsGateway always reports success=false and the log stays
        // QUEUED_PENDING_GATEWAY — that's correct, not a failure: nothing
        // was genuinely attempted until a real gateway is configured.
        if ($result['success']) {
            $log->update([
                'status' => 'SENT',
                'gateway' => $result['gateway'],
                'cost' => $result['cost'],
                'sent_at' => now(),
            ]);
        } elseif ($result['gateway'] !== 'null') {
            // a real gateway was tried and refused: record it so the office can see and retry
            $log->update(['status' => 'FAILED', 'gateway' => $result['gateway']]);
        }
    }
}
