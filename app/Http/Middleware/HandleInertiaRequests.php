<?php

namespace App\Http\Middleware;

use App\Console\Commands\GenerateAnniversaryNotifications;
use App\Models\ListingUser;
use Illuminate\Http\Request;
use App\Support\Media;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data every Vue page receives: the logged in user, the notifications bell, flash messages.
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
                    'avatar' => Media::url($user->profilePicture),
                    'isAdmin' => $user->isAdmin == 1,
                    'isDemo' => (bool) $user->is_demo,
                    // null = follow the device
                    'darkMode' => is_null($user->dark_mode) ? null : (bool) $user->dark_mode,
                ] : null,
            ],
            'notifications' => fn () => $user ? $this->notifications($user) : null,
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }

    // Same data as the bell in the old layout: albums not seen yet and "on this day" memories
    private function notifications($user): array
    {
        $memories = GenerateAnniversaryNotifications::getMemoriesOfTheDay(true)[$user->id] ?? [];

        return [
            'unseen' => ListingUser::with('listing')
                ->where('user_id', $user->id)
                ->where('seen', false)
                ->get()
                ->filter(fn ($row) => $row->listing)
                ->map(fn ($row) => ['id' => $row->listing->id, 'title' => $row->listing->title])
                ->values(),
            'memories' => collect($memories)
                ->map(fn ($listing) => ['id' => $listing->id, 'title' => $listing->title, 'years' => $listing->diffInYears])
                ->values(),
        ];
    }
}
