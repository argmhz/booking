<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'notifications' => [
                        'unread_count' => $user->unreadNotifications()->count(),
                        'items' => $user->notifications()
                            ->latest()
                            ->limit(10)
                            ->get()
                            ->map(fn ($notification): array => [
                                'id' => $notification->id,
                                'title' => (string) data_get($notification->data, 'title', 'Notifikation'),
                                'message' => (string) data_get($notification->data, 'message', ''),
                                'url' => (string) data_get($notification->data, 'url', ''),
                                'created_at' => $notification->created_at,
                                'read_at' => $notification->read_at,
                            ])
                            ->values(),
                    ],
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
