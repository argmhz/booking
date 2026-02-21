<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_id !== $request->user()?->id || $notification->notifiable_type !== $request->user()?->getMorphClass()) {
            abort(403);
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $url = data_get($notification->data, 'url');

        if (is_string($url) && $url !== '') {
            return redirect($url);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back()->with('status', 'Notifikationer markeret som laest.');
    }
}
