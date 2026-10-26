<?php

namespace App\Http\Middleware;

use App\Console\Commands\GenerateAnniversaryNotifications;
use App\Models\ListingUser;
use App\Notifications\CommentLiked;
use App\Notifications\Mentioned;
use App\Notifications\Reacted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            // English or Italian: the pages translate themselves with lang/it.json
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => Media::url($user->profilePicture),
                    'isAdmin' => $user->isAdmin == 1,
                    'canPostAlbums' => $user->canPostAlbums(),
                    'canUseGym' => $user->canUseGym(),
                    'isDemo' => (bool) $user->is_demo,
                    // null = follow the device
                    'darkMode' => is_null($user->dark_mode) ? null : (bool) $user->dark_mode,
                ] : null,
            ],
            'notifications' => fn () => $user ? $this->notifications($user) : null,
            'flash' => [
                // Written in English by the controllers, translated here into the reader's language
                'message' => fn () => ($message = $request->session()->get('message')) ? __($message) : null,
                // Password reset emails ("We have emailed your password reset link")
                'status' => fn () => $request->session()->get('status'),
                // Maintenance page: output of the commands, a newly created invite link
                'output' => fn () => $request->session()->get('output'),
                'inviteLink' => fn () => $request->session()->get('inviteLink'),
            ],
        ];
    }

    // "Zecca liked your comment" from the last 30 days (also used by the Blade layout's bell)
    public static function likeNotifications($user): array
    {
        // Right after a deploy, before its migration runs, the table doesn't exist yet. Every page
        // shows the bell, the maintenance page too: it must keep working to run the migration
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        // Likes on your comments, reactions to your albums and posts, @mentions
        return $user->notifications()
            ->whereIn('type', [CommentLiked::class, Reacted::class, Mentioned::class])
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => match ($notification->type) {
                Mentioned::class => [
                    'id' => $notification->id,
                    'kind' => $notification->data['kind'] ?? 'mention', // or reply
                    'liker' => $notification->data['author_name'],
                    'excerpt' => $notification->data['excerpt'],
                    'title' => $notification->data['title'],
                    'url' => $notification->data['url'],
                    'read' => ! is_null($notification->read_at),
                ],
                Reacted::class => [
                    'id' => $notification->id,
                    'kind' => $notification->data['target_type'], // album or post
                    'liker' => $notification->data['reactor_name'],
                    'emoji' => $notification->data['emoji'],
                    'title' => $notification->data['title'],
                    'url' => $notification->data['url'],
                    'read' => ! is_null($notification->read_at),
                ],
                default => [
                    'id' => $notification->id,
                    'kind' => 'comment',
                    'liker' => $notification->data['liker_name'],
                    'excerpt' => $notification->data['excerpt'],
                    'title' => $notification->data['title'],
                    'url' => $notification->data['url'],
                    'read' => ! is_null($notification->read_at),
                ],
            })
            ->all();
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
            'likes' => self::likeNotifications($user),
        ];
    }
}
