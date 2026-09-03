<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return response([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()->notifications()->latest()->paginate(30),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
