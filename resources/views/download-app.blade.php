<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Green Valley BMS — অ্যাপ ডাউনলোড</title>
<style>
    body { margin: 0; font-family: system-ui, "Noto Sans Bengali", sans-serif; background: #f1f7f6; color: #16324f; }
    main { max-width: 440px; margin: 0 auto; padding: 28px 18px 40px; }
    .card { background: #fff; border: 1px solid #cfe3e0; border-radius: 14px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    h1 { font-size: 21px; margin: 0 0 4px; }
    .muted { color: #5b7185; font-size: 13px; }
    .btn { display: block; margin: 20px 0 6px; padding: 15px; text-align: center; border-radius: 10px;
           background: #0F766E; color: #fff; font-weight: 600; text-decoration: none; font-size: 17px; }
    ol { padding-left: 20px; line-height: 1.7; }
    .warn { background: #fff7e6; border: 1px solid #f0d9a8; border-radius: 10px; padding: 12px; font-size: 14px; margin-top: 16px; }
    code { font-size: 11px; word-break: break-all; }
</style>
</head>
<body>
<main>
    <div class="card">
        <h1>🏢 Green Valley BMS</h1>
        <div class="muted">Android অ্যাপ</div>

        @if ($available)
            <a class="btn" href="{{ $apkUrl }}">⬇ অ্যাপ ডাউনলোড করুন</a>
            <div class="muted">
                সংস্করণ {{ $info['versionName'] ?? '—' }} · {{ $info['size_mb'] }} MB
                @if (!empty($info['builtAt'])) · {{ $info['builtAt'] }} @endif
            </div>

            <h2 style="font-size:16px;margin-top:22px">ইনস্টল করবেন যেভাবে</h2>
            <ol>
                <li>উপরের <b>ডাউনলোড</b> বাটন চাপুন।</li>
                <li>ফাইল খুলুন। ফোন বললে <b>"এই উৎস থেকে ইনস্টলের অনুমতি"</b> দিন (শুধু একবার)।</li>
                <li><b>ইনস্টল</b> চাপুন, তারপর অ্যাপ খুলুন।</li>
                <li>প্রথমবার সার্ভারের ঠিকানা চাইলে যে ওয়েবসাইট থেকে এই পেজ খুলেছেন সেই ঠিকানা দিন।</li>
            </ol>

            <div class="warn">
                ⚠ এটা Play Store-এর অ্যাপ নয়, তাই ফোন "অজানা অ্যাপ" বলে সতর্ক করতে পারে। শুধু নিজের কমিটির দেওয়া এই লিংক থেকেই নামান।
            </div>

            <p class="muted" style="margin-top:18px">ফাইলের SHA-256 (যাচাইয়ের জন্য):<br><code>{{ $info['sha256'] }}</code></p>
        @else
            <p style="margin-top:20px">অ্যাপ ফাইল এখনো তৈরি/আপলোড করা হয়নি। অ্যাডমিনের সাথে যোগাযোগ করুন।</p>
        @endif
    </div>
</main>
</body>
</html>
