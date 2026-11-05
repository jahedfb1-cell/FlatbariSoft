<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/** The signed-in person's own notifications: the latest ones, how many are unread, mark one or all as read. */
class NotificationController extends Controller
{
    private function ready(): bool
    {
        return Schema::hasTable('notifications');
    }

    public function index()
    {
        if (! $this->ready()) {
            return response()->json(['unread' => 0, 'items' => []]);
        }
        $user = Auth::user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(50)->get()->map(fn ($n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'link' => $n->data['link'] ?? null,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function read(string $id)
    {
        abort_unless($this->ready(), 404);
        Auth::user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread' => Auth::user()->unreadNotifications()->count()]);
    }

    public function readAll()
    {
        if ($this->ready()) {
            Auth::user()->unreadNotifications->markAsRead();
        }

        return response()->json(['unread' => 0]);
    }
}
