<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

// Delivers phone notifications through the browsers' push services (Apple, Google, Mozilla),
// encrypted for each device. Tests replace it with a fake that only records them
class PushSender
{
    // [['endpoint' => ..., 'success' => bool, 'reason' => ...]]
    public function send(Collection $subscriptions, array $payload): array
    {
        $keys = VapidKeys::get();
        if (! $keys || $subscriptions->isEmpty()) {
            return [];
        }

        // TTL: a phone that's off still gets it within a day; "high" wakes it up like a message;
        // topic: a newer notification about the same thing replaces one still waiting
        $options = ['TTL' => 86400, 'urgency' => 'high'];
        $topic = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $payload['tag'] ?? ''), 0, 32);
        if ($topic !== '') {
            $options['topic'] = $topic;
        }
        $webPush = new WebPush(
            ['VAPID' => ['subject' => 'mailto:info@theboise.it', 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]],
            $options,
        );
        $webPush->setReuseVAPIDHeaders(true);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                // The modern format unless the browser said otherwise (old Chrome/Edge: aesgcm)
                'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
            ]), json_encode($payload));
        }

        $results = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $results[] = ['endpoint' => $endpoint, 'success' => $report->isSuccess(), 'reason' => $report->isSuccess() ? null : $report->getReason()];

            if ($report->isSuccess()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->update(['last_used_at' => now()]);
            } elseif ($report->isSubscriptionExpired()) {
                // The device turned them off or the app was removed: forget it
                PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
            } else {
                Log::warning('Phone notification not delivered', ['reason' => $report->getReason()]);
            }
        }

        return $results;
    }
}
