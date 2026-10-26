<?php

namespace App\Http\Controllers;

class AppDownloadController extends Controller
{
    /** Public landing page: what to tap, how to install, and the download button. */
    public function page()
    {
        $apk = config('mobile_app.apk_path');
        $available = is_file($apk);
        $info = [];

        if ($available) {
            $versionFile = dirname($apk).DIRECTORY_SEPARATOR.'app-version.json';
            $info = is_file($versionFile) ? (json_decode((string) file_get_contents($versionFile), true) ?: []) : [];
            $info['size_mb'] = round(filesize($apk) / 1048576, 1);
            $info['sha256'] ??= hash_file('sha256', $apk);
        }

        return view('download-app', ['available' => $available, 'info' => $info, 'apkUrl' => url('/download/apk')]);
    }

    public function apk()
    {
        $apk = config('mobile_app.apk_path');
        abort_unless(is_file($apk), 404, 'অ্যাপ ফাইল এখনো তৈরি হয়নি।');

        return response()->download($apk, config('mobile_app.download_name'), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-cache, must-revalidate', // a new build must not be served from a stale cache
        ]);
    }
}
