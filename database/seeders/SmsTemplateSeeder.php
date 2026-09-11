<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'BILL_GENERATED' => 'প্রিয় গ্রাহক, আপনার ফ্ল্যাট {{flat_no}}-এর {{month}} মাসের বিল ৳{{amount}} জেনারেট হয়েছে। পরিশোধের শেষ তারিখ {{due_date}}। - Green Valley',
            'PAYMENT_RECEIVED' => 'প্রিয় গ্রাহক, ফ্ল্যাট {{flat_no}}-এর জন্য ৳{{amount}} পেমেন্ট গ্রহণ করা হয়েছে। রিসিট নং: {{receipt_no}}। - Green Valley',
            'DUE_REMINDER' => 'প্রিয় গ্রাহক, ফ্ল্যাট {{flat_no}}-এর বকেয়া ৳{{amount}} এখনো অপরিশোধিত। দ্রুত পরিশোধ করুন। - Green Valley',
        ];

        foreach ($templates as $eventKey => $body) {
            SmsTemplate::firstOrCreate(['event_key' => $eventKey], ['body_bn' => $body, 'is_active' => true]);
        }
    }
}
