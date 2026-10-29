<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\SmsLog;
use App\Services\SettingsService;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /** The office settings, and whether a real SMS provider is configured on the server at all. */
    public function show()
    {
        return response()->json([
            'settings' => $this->settings->all(),
            'sms_gateway' => config('sms.driver') === 'http' && config('sms.url') ? 'CONFIGURED' : 'NOT_CONFIGURED',
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        return response()->json(['settings' => $this->settings->update($request->validated())]);
    }

    /** The last messages the system tried to send, newest first. */
    public function smsLogs()
    {
        return SmsLog::latest('id')->limit(30)->get(['id', 'mobile', 'message', 'event_key', 'status', 'created_at', 'sent_at']);
    }
}
