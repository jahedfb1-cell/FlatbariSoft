<?php

namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Generic "call the provider's URL" gateway; see config/sms.php. */
class HttpSmsGateway implements SmsGatewayInterface
{
    public function send(string $mobile, string $message): array
    {
        $url = (string) config('sms.url');
        if ($url === '') {
            Log::warning('[HttpSmsGateway] SMS_URL is not set; nothing sent.');

            return ['success' => false, 'gateway' => 'http', 'cost' => 0];
        }

        $to = $this->normalise($mobile);
        $fields = ['key' => (string) config('sms.key'), 'sender' => (string) config('sms.sender'), 'to' => $to, 'message' => $message];

        // the placeholders become the (URL-encoded) values; a POST sends the same query string as form fields
        $final = strtr($url, array_combine(
            array_map(fn ($k) => '{'.$k.'}', array_keys($fields)),
            array_map('rawurlencode', $fields),
        ));

        try {
            $http = Http::timeout((int) config('sms.timeout'));
            if (config('sms.method') === 'POST') {
                [$base, $query] = array_pad(explode('?', $final, 2), 2, '');
                parse_str($query, $form);
                $response = $http->asForm()->post($base, $form);
            } else {
                $response = $http->get($final);
            }
        } catch (\Throwable $e) {
            Log::error('[HttpSmsGateway] '.$e->getMessage());

            return ['success' => false, 'gateway' => 'http', 'cost' => 0];
        }

        $expected = (string) config('sms.success');
        $ok = $response->successful() && ($expected === '' || str_contains($response->body(), $expected));
        if (! $ok) {
            Log::warning('[HttpSmsGateway] provider refused: HTTP '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        return ['success' => $ok, 'gateway' => 'http', 'cost' => 0];
    }

    /** Bangladeshi numbers go out as 8801XXXXXXXXX (what the providers expect). */
    private function normalise(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        return match (true) {
            str_starts_with($digits, '880') => $digits,
            str_starts_with($digits, '0') => '88'.$digits,
            default => '880'.$digits,
        };
    }
}
