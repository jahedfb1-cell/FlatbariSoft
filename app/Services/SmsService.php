<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Flat;
use App\Models\SmsLog;
use App\Models\SmsTemplate;

/**
 * Called by billing/payment/complaint flows to notify a resident - never talks to a gateway directly (see
 * SmsGatewayInterface). Every message is written to sms_logs first. It is only handed to the gateway when
 * the office has switched "Send SMS" on in Settings; otherwise it stays in the log as QUEUED_PENDING_GATEWAY,
 * so nothing is ever sent (or charged) by accident.
 *
 * Sending is immediate (dispatchSync), not queued: the shared host runs no queue worker, and a bulk run
 * (reminders for 45 flats) is a scheduled command, not a web request, so waiting on the gateway is fine.
 */
class SmsService
{
    public function __construct(private readonly SettingsService $settings) {}

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

        if ($this->settings->bool('sms_enabled')) {
            SendSmsJob::dispatchSync($log->id);
        }

        return $log->fresh();
    }

    /**
     * The same message to everyone who should hear about a flat: its current owner(s) and tenant. A person
     * with no mobile number is skipped; the same number is never texted twice.
     *
     * @param  array<string, string>  $variables
     * @return list<SmsLog>
     */
    public function notifyFlat(Flat $flat, string $eventKey, array $variables = []): array
    {
        $flat->loadMissing('currentOwners.person', 'currentTenant.person');
        $numbers = collect($flat->currentOwners)->concat($flat->currentTenant)
            ->map(fn ($party) => $party->person?->mobile)->filter()->unique()->values();

        return $numbers->map(fn ($mobile) => $this->sendFromTemplate($eventKey, $mobile, $variables + ['flat_no' => (string) $flat->flat_no]))->all();
    }
}
