<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\Notify;
use App\Support\PushSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// Phone notifications: the devices where they're on, a test, and what each person wants
class PushController extends Controller
{
    // This device turned them on (also sent again each time the app opens, to stay up to date).
    // A device belongs to whoever is logged in on it
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:2000|starts_with:https://',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'contentEncoding' => 'nullable|in:aesgcm,aes128gcm',
        ]);

        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'device' => Notify::deviceName($request->userAgent()),
        ]);

        return response()->json(['devices' => $this->devices($request)]);
    }

    // This device turned them off, or someone logs out on it
    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2000']);
        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))->where('user_id', $request->user()->id)->delete();

        return response()->json(['devices' => $this->devices($request)]);
    }

    // Remove a device from the list (e.g. an old phone)
    public function forget(Request $request, PushSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $subscription->delete();

        return back()->with('message', 'Device removed');
    }

    public function test(Request $request)
    {
        $subscriptions = $request->user()->pushSubscriptions()->get();
        $results = app(PushSender::class)->send($subscriptions, [
            'title' => 'It works! 🎉',
            'body' => 'This is how TheBoise notifications look.',
            'url' => '/account',
            'tag' => 'test',
        ]);
        $delivered = collect($results)->where('success', true)->count();

        return back()->with('message', match (true) {
            ! $subscriptions->count() => 'Turn on notifications on a device first',
            $delivered === count($results) => "Test notification sent to {$delivered} " . ($delivered === 1 ? 'device' : 'devices'),
            default => "Sent to {$delivered} of " . count($results) . ' devices: the others turned notifications off',
        });
    }

    // What to receive on the phone and by email
    public function settings(Request $request)
    {
        $rules = [];
        foreach (Notify::TYPES as $type => $info) {
            $rules["settings.{$type}.push"] = 'boolean';
            if ($info['email'] !== null) {
                $rules["settings.{$type}.email"] = 'boolean';
            }
        }
        $data = $request->validate($rules);

        if (Schema::hasColumn('users', 'notification_settings')) {
            $request->user()->update(['notification_settings' => collect($data['settings'] ?? [])
                ->only(array_keys(Notify::TYPES))
                ->map(fn ($channels) => collect($channels)->only(['push', 'email'])->map(fn ($on) => (bool) $on))
                ->all()]);
        }

        return back();
    }

    public static function devices(Request $request): array
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return [];
        }

        return $request->user()->pushSubscriptions()->latest()->get()->map(fn (PushSubscription $subscription) => [
            'id' => $subscription->id,
            'device' => $subscription->device ?? 'Browser',
            'endpointHash' => $subscription->endpoint_hash,
            'since' => $subscription->created_at?->toIso8601String(),
        ])->all();
    }
}
