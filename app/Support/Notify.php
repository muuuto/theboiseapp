<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

// What each person wants to receive, on the phone and by email (account page), and sending the
// phone notifications. Without a choice saved: emails as they've always been, phone notifications
// on (they only arrive on devices where the person turned them on)
class Notify
{
    // type => [label, email: default (null = there's no email for it), push: default]
    public const TYPES = [
        'album' => ['label' => 'New albums you\'re in', 'email' => true, 'push' => true],
        'comment' => ['label' => 'Comments on albums you\'re in', 'email' => true, 'push' => true],
        'forum' => ['label' => 'Forum: replies to your posts and shared posts', 'email' => true, 'push' => true],
        'like' => ['label' => 'Likes on your comments', 'email' => null, 'push' => true],
        'expenses' => ['label' => 'Shared expenses: new expenses and payments', 'email' => true, 'push' => true],
        'memories' => ['label' => '"On this day" memories', 'email' => true, 'push' => true],
    ];

    private static ?bool $ready = null;

    public static function wants(User $user, string $type, string $channel): bool
    {
        if ($user->is_demo) {
            return false;
        }
        $default = self::TYPES[$type][$channel] ?? null;
        if ($default === null) {
            return false;
        }

        return (bool) ($user->notification_settings[$type][$channel] ?? $default);
    }

    // Everyone's choices, filled in with the defaults (for the account page)
    public static function settings(User $user): array
    {
        return collect(self::TYPES)->map(fn ($type, $key) => [
            'key' => $key,
            'label' => $type['label'],
            'email' => $type['email'] === null ? null : self::wants($user, $key, 'email'),
            'push' => self::wants($user, $key, 'push'),
        ])->values()->all();
    }

    // Phone notification to these people (those who want this type), after the page is answered so
    // nobody waits for Apple or Google. $message: title, body, url, tag (same tag = replaces), image
    public static function push(User|iterable $users, string $type, array $message): void
    {
        if (! (self::$ready ??= Schema::hasTable('push_subscriptions'))) {
            return;
        }

        $ids = collect($users instanceof User ? [$users] : $users)
            ->filter(fn (User $user) => self::wants($user, $type, 'push'))
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $send = function () use ($ids, $message) {
            $subscriptions = PushSubscription::whereIn('user_id', $ids)->get();
            if ($subscriptions->isNotEmpty()) {
                app(PushSender::class)->send($subscriptions, $message + ['url' => '/']);
            }
        };

        // Scheduled jobs (and tests) send right away; a page sends once the answer is out
        app()->runningInConsole() ? $send() : app()->terminating($send);
    }

    // Device name from the browser, to recognise it in the account page
    public static function deviceName(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Browser',
        };
    }
}
