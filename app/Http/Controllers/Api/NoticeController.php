<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public function index()
    {
        return Notice::whereNotNull('published_at')->latest('published_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => ['nullable', Rule::in(['ALL', 'OWNERS', 'TENANTS'])],
        ]);

        $notice = Notice::create(array_merge($data, [
            'audience' => $data['audience'] ?? 'ALL',
            'published_at' => now(),
            'created_by' => Auth::id(),
        ]));

        return response()->json($notice, 201);
    }
}
