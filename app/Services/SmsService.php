<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\SmsTemplate;

/**
 * Called by billing/payment/complaint flows to notify a resident — never
 * talks to a gateway directly (see SmsGatewayInterface). Queues the actual
 * send so a slow/unreachable gateway never blocks the request that
 * triggered the notification.
 */
class SmsService
{
    /**
     * @param  array<string, string>  $variables  {{key}} placeholders in the template body
     */
    public function sendFromTemplate(string $eventKey, string $mobile, array $variables = []): SmsLog
    {
        $template = SmsTemplate::where('event_key', $eventKey)->where('is_active', true)->first();
        $body = $template?->body_bn ?? $eventKey;

        foreach ($variables as $key => $value) {
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        return $this->send($mobile, $body, $eventKey);
    }

    public function send(string $mobile, string $message, ?string $eventKey = null): SmsLog
    {
        $log = SmsLog::create([
            'mobile' => $mobile,
            'message' => $message,
            'event_key' => $eventKey,
            'status' => 'QUEUED_PENDING_GATEWAY',
        ]);

        SendSmsJob::dispatch($log->id);

        return $log;
    }
}
