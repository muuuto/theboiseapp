<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\ExpenseGroup;
use App\Models\Listing;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushSender;
use App\Support\Settings;
use App\Support\VapidKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Phone notifications: devices, preferences, and what each action sends.
// The real delivery (Apple, Google...) is replaced by a fake that records it
class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PushSender::class, new class($this->sent) extends PushSender {
            public function __construct(private array &$sent) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                foreach ($subscriptions as $subscription) {
                    $this->sent[] = ['user' => $subscription->user_id, 'payload' => $payload];
                }

                return $subscriptions->map(fn ($s) => ['endpoint' => $s->endpoint, 'success' => true, 'reason' => null])->all();
            }
        });
    }

    // --- Devices ---

    public function test_a_device_turns_notifications_on_and_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
            ->postJson('/push/subscriptions', $this->subscription('https://web.push.apple.com/abc'))
            ->assertOk()
            ->assertJsonPath('devices.0.device', 'iPhone');
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->deleteJson('/push/subscriptions', ['endpoint' => 'https://web.push.apple.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_device_belongs_to_whoever_is_logged_in_on_it(): void
    {
        [$anna, $zecca] = User::factory()->count(2)->create();

        $this->actingAs($anna)->postJson('/push/subscriptions', $this->subscription('https://fcm.googleapis.com/x'));
        $this->actingAs($zecca)->postJson('/push/subscriptions', $this->subscription('https://fcm.googleapis.com/x'));

        $this->assertSame(1, PushSubscription::count());
        $this->assertEquals($zecca->id, PushSubscription::first()->user_id);
    }

    public function test_bad_devices_and_the_demo_account_are_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push/subscriptions', $this->subscription('http://evil.example/x'))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['is_demo' => true]))->postJson('/push/subscriptions', $this->subscription('https://fcm.googleapis.com/x'))->assertForbidden();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_someone_elses_device_cant_be_removed(): void
    {
        [$anna, $zecca] = User::factory()->count(2)->create();
        $device = $this->device($anna);

        $this->actingAs($zecca)->delete("/push/subscriptions/{$device->id}")->assertNotFound();
        $this->actingAs($anna)->delete("/push/subscriptions/{$device->id}")->assertRedirect();
        $this->assertModelMissing($device);
    }

    public function test_account_page_has_the_key_and_the_choices(): void
    {
        $user = User::factory()->create();
        $this->device($user);

        $this->actingAs($user)->get('/account')->assertInertia(fn ($page) => $page
            ->where('push.publicKey', VapidKeys::publicKey())
            ->where('push.settings.0.key', 'album')
            ->where('push.settings.0.push', true)
            ->where('push.settings.3.email', null) // likes: no email
            ->has('push.devices', 1));
        // The keys are made once and kept
        $this->assertSame(VapidKeys::publicKey(), Settings::get(VapidKeys::SETTING)['publicKey']);
        $this->actingAs(User::factory()->create(['is_demo' => true]))->get('/account')->assertInertia(fn ($page) => $page->where('push', null));
    }

    public function test_test_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/push/test')->assertSessionHas('message', 'Turn on notifications on a device first');

        $this->device($user);
        $this->actingAs($user)->post('/push/test')->assertSessionHas('message', 'Test notification sent to 1 device');
        $this->assertSame('It works! 🎉', $this->sent[0]['payload']['title']);
    }

    // --- What sends what ---

    public function test_new_album_notifies_the_people_in_it_by_their_choice(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Muto']);
        $anna = User::factory()->create();
        $quiet = User::factory()->create(['notification_settings' => ['album' => ['push' => false, 'email' => true]]]);
        $phoneOnly = User::factory()->create(['notification_settings' => ['album' => ['push' => true, 'email' => false]]]);
        foreach ([$admin, $anna, $quiet, $phoneOnly] as $user) {
            $this->device($user);
        }

        $this->actingAs($admin)->post('/listings', [
            'title' => 'Sicilia 2026', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-08', 'location' => 'Palermo',
            'albumLink' => 'https://www.amazon.it/photos/share/abc', 'tags' => ['beach'], 'description' => '<p>x</p>', 'cover' => $this->fakeCover(),
            'people' => [$admin->id, $anna->id, $quiet->id, $phoneOnly->id],
        ])->assertSessionHasNoErrors();

        $album = Listing::firstWhere('title', 'Sicilia 2026');
        $this->assertEqualsCanonicalizing([$anna->id, $phoneOnly->id], array_column($this->sent, 'user'));
        $this->assertSame('New album: Sicilia 2026', $this->sent[0]['payload']['title']);
        $this->assertSame("/listings/{$album->id}", $this->sent[0]['payload']['url']);
        // Emails: Anna and the one who only wants emails, not the phone-only one (nor Muto, who posted it)
        $this->assertEqualsCanonicalizing([$anna->email, $quiet->email], $this->emailRecipients());
    }

    public function test_album_comment_and_like(): void
    {
        [$anna, $zecca] = User::factory()->count(2)->create();
        $album = Listing::factory()->create(['title' => 'Sicilia 2026']);
        $album->users()->attach([$anna->id, $zecca->id]);
        $this->device($anna);
        $this->device($zecca);

        $this->actingAs($zecca)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => 'Bellissimo!']);
        $comment = Comment::first();
        $this->assertSame([$anna->id], array_column($this->sent, 'user'));
        $this->assertSame("/listings/{$album->id}#comment-{$comment->id}", $this->sent[0]['payload']['url']);
        $this->assertStringNotContainsString('Bellissimo', json_encode($this->sent[0]['payload'])); // nothing private on a locked screen

        $this->actingAs($anna)->post('/likes', ['type' => 'album_comment', 'id' => $comment->id]);
        $this->assertSame($zecca->id, $this->sent[1]['user']);
        $this->assertStringContainsString('liked your comment', $this->sent[1]['payload']['title']);
    }

    public function test_expense_notifies_with_the_share(): void
    {
        [$me, $anna] = User::factory()->count(2)->create();
        $group = ExpenseGroup::create(['name' => 'Casa', 'created_by' => $me->id]);
        $group->members()->attach([$me->id, $anna->id]);
        $this->device($anna);

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", [
            'description' => 'Spesa', 'amount' => '30', 'spent_on' => '2026-10-01', 'paid_by' => $me->id,
            'split_type' => 'equal', 'split' => [$me->id => true, $anna->id => true],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Spesa: your share €15.00', $this->sent[0]['payload']['title']);
    }

    public function test_memories_of_the_day(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 3));
        $anna = User::factory()->create();
        $album = Listing::factory()->create(['title' => 'Sicilia 2023', 'dateFrom' => '2023-08-03', 'dateTo' => '2023-08-05']);
        $album->users()->attach($anna);
        $this->makeActive($anna);
        $this->device($anna);

        $this->artisan('notifications:anniversaries')->assertSuccessful();

        $this->assertSame('On this day: Sicilia 2023', $this->sent[0]['payload']['title']);
        $this->assertSame('3 years ago. Relive it!', $this->sent[0]['payload']['body']);
    }

    public function test_choices_are_saved_and_emails_can_be_turned_off(): void
    {
        [$anna, $zecca] = User::factory()->count(2)->create();
        $album = Listing::factory()->create();
        $album->users()->attach([$anna->id, $zecca->id]);

        $this->actingAs($anna)->put('/push/settings', ['settings' => ['comment' => ['push' => true, 'email' => false], 'like' => ['push' => false]]]);
        // assertEquals: MySQL stores the JSON keys in its own order
        $this->assertEquals(['comment' => ['push' => true, 'email' => false], 'like' => ['push' => false]], $anna->fresh()->notification_settings);

        $this->actingAs($zecca)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => 'Ciao']);
        $this->assertSame([], $this->emailRecipients());
    }

    private function subscription(string $endpoint): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyOfTheDevice', 'auth' => 'authSecret'], 'contentEncoding' => 'aes128gcm'];
    }

    private function device(User $user): PushSubscription
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/' . $user->id;

        return PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'key', 'auth_token' => 'auth', 'device' => 'Android',
        ]);
    }

    private function emailRecipients(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->flatMap(fn ($message) => $message->getEnvelope()->getRecipients())
            ->map(fn ($address) => $address->getAddress())
            ->all();
    }
}
