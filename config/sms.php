<?php

/**
 * SMS delivery. Nothing is sent until SMS_DRIVER=http and the provider details below are filled in on the
 * server (.env), and the office has switched "Send SMS" on in Settings. The HTTP driver speaks the plain
 * "call a URL with the number and the message" style that most Bangladeshi bulk-SMS services (BulkSMSBD,
 * SSL Wireless, Alpha SMS, Greenweb ...) offer, so changing provider is a change of SMS_URL, not of code.
 *
 *   SMS_URL      with the placeholders {key} {sender} {to} {message}, e.g.
 *                http://bulksmsbd.net/api/smsapi?api_key={key}&type=text&number={to}&senderid={sender}&message={message}
 *   SMS_METHOD   GET (default) or POST (the same placeholders are sent as form fields)
 *   SMS_SUCCESS  text the reply must contain to count as delivered, e.g. 202 or SUCCESS (empty = any 2xx reply)
 */
return [
    'driver' => env('SMS_DRIVER', 'null'),
    'url' => env('SMS_URL'),
    'method' => strtoupper((string) env('SMS_METHOD', 'GET')),
    'key' => env('SMS_API_KEY'),
    'sender' => env('SMS_SENDER_ID'),
    'success' => env('SMS_SUCCESS'),
    'timeout' => (int) env('SMS_TIMEOUT', 15),
];
