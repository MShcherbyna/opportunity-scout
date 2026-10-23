<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'threadsConnection' => fn () => $this->threadsConnectionFor($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The signed-in user's Threads connection summary for the disconnected banner.
     *
     * @return array{status: string, disconnectReason: string|null, username: string}|null
     */
    private function threadsConnectionFor(Request $request): ?array
    {
        $connection = $request->user()?->threadsConnection()->first();

        if ($connection === null) {
            return null;
        }

        return [
            'status' => $connection->status->value,
            'disconnectReason' => $connection->disconnect_reason?->value,
            'username' => $connection->username,
        ];
    }
}
