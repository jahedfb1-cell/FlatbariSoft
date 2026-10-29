<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\BackupService;
use Illuminate\Support\Facades\Auth;

class BackupController extends Controller
{
    public function download(BackupService $backup)
    {
        // a download is itself an event worth remembering
        AuditLog::create(['user_id' => Auth::id(), 'event' => 'backup', 'auditable_type' => 'Database', 'auditable_id' => 0, 'ip' => request()->ip()]);

        $name = 'green-valley-backup-'.now('Asia/Dhaka')->format('Y-m-d_His').'.sql';

        return response()->streamDownload(function () use ($backup) {
            $backup->dump(function (string $chunk) {
                echo $chunk;
            });
        }, $name, ['Content-Type' => 'application/sql']);
    }
}
