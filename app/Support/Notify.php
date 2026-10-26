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
        'mention' => ['label' => 'Replies to your comments and mentions (@you)', 'email' => null, 'push' => true],
        'like' => ['label' => 'Likes on your comments, reactions to your albums and posts', 'email' => null, 'push' => true],
        'expenses' => ['label' => 'Shared expenses: new expenses and payments', 'email' => true, 'push' => true],
        'trips' => ['label' => 'Trips: invitations, dates decided, reminders', 'email' => true, 'push' => true],
        'memories' => ['label' => '"On this day" memories', 'email' => true, 'push' => true],
        'wrapped' => ['label' => 'Your yearly Wrapped (end of December)', 'email' => true, 'push' => true],
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
            'label' => __($type['label']),
            'email' => $type['email'] === null ? null : self::wants($user, $key, 'email'),
            'push' => self::wants($user, $key, 'push'),
        ])->values()->all();
    }

    // Phone notification to these people (those who want this type), after the page is answered so
    // nobody waits for Apple or Google. $message: title, body, url, tag (same tag = replaces), image;
    // a function returning it is called in each person's language
    public static function push(User|iterable $users, string $type, array|callable $message): void
    {
        if (! (self::$ready ??= Schema::hasTable('push_subscriptions'))) {
            return;
        }

        $people = collect($users instanceof User ? [$users] : $users)
            ->filter(fn (User $user) => self::wants($user, $type, 'push'));
        if ($people->isEmpty()) {
            return;
        }

        // One message per language
        $messages = $people->groupBy(fn (User $user) => Locale::of($user))->map(fn ($group) => [
            'ids' => $group->pluck('id'),
            'message' => is_callable($message) ? Locale::for($group->first(), $message) : $message,
        ]);

        $send = function () use ($messages) {
            foreach ($messages as $batch) {
                $subscriptions = PushSubscription::whereIn('user_id', $batch['ids'])->get();
                if ($subscriptions->isNotEmpty()) {
                    app(PushSender::class)->send($subscriptions, $batch['message'] + ['url' => '/']);
                }
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
