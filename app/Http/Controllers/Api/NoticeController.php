<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Models\Notice;
use Illuminate\Support\Facades\Auth;

class NoticeController extends Controller
{
    public function index()
    {
        return Notice::whereNotNull('published_at')->latest('published_at')->get();
    }

    public function store(StoreNoticeRequest $request)
    {
        $data = $request->validated();

        $notice = Notice::create(array_merge($data, [
            'audience' => $data['audience'] ?? 'ALL',
            'published_at' => now(),
            'created_by' => Auth::id(),
        ]));

        return response()->json($notice, 201);
    }
}
