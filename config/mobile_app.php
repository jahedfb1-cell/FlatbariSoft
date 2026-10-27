<?php

/**
 * The Android (WebView) app download. scripts/build-android.ps1 builds the APK and
 * drops it, plus a small app-version.json beside it, into public/downloads/.
 * The APK is a build artefact: it is gitignored, and on the live host it is
 * simply uploaded to the same folder.
 */
return [
    'apk_path' => env('MOBILE_APK_PATH', public_path('downloads/green-valley-bms.apk')),
    'download_name' => 'green-valley-bms.apk',
];
