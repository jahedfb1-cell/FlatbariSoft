<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Newest first, 50 a page; ?model=Payment, ?user=ID, ?event=created|updated|deleted, ?from= ?to= (dates), ?id=record id. */
    public function index(Request $request)
    {
        $v = $request->validate([
            'model' => ['nullable', 'string', 'max:60'], 'user' => ['nullable', 'integer'], 'event' => ['nullable', 'in:created,updated,deleted'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'id' => ['nullable', 'integer'],
        ]);

        $page = AuditLog::with('user.person')
            ->when($v['model'] ?? null, fn ($q, $m) => $q->where('auditable_type', $m))
            ->when($v['id'] ?? null, fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($v['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($v['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($v['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($v['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AuditLog $l) => [
                'id' => $l->id, 'when' => $l->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i:s'),
                'user' => $l->user?->displayName(), 'event' => $l->event,
                'model' => $l->auditable_type, 'record_id' => $l->auditable_id,
                'old' => $l->old_values, 'new' => $l->new_values, 'ip' => $l->ip,
            ]),
            'models' => AuditLog::select('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
        ]);
    }
}
