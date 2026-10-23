<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\Reaction;
use App\Models\User;
use App\Support\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Emoji reactions to albums and forum posts: one each, the poster hears about it
class ReactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;
    private User $anna;
    private Listing $album;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poster = User::factory()->create(['name' => 'Muto']);
        $this->anna = User::factory()->create(['name' => 'Anna']);
        $this->album = Listing::factory()->create(['title' => 'Sicilia', 'user_id' => $this->poster->id]);
        $this->album->users()->attach([$this->poster->id, $this->anna->id]);
    }

    private function react(User $user, string $type, int $id, string $emoji)
    {
        return $this->actingAs($user)->post('/reactions', ['type' => $type, 'id' => $id, 'emoji' => $emoji]);
    }

    public function test_one_reaction_each_changed_or_taken_back(): void
    {
        $this->react($this->anna, 'album', $this->album->id, '❤️')->assertRedirect();
        $this->react($this->poster, 'album', $this->album->id, '❤️');
        $this->actingAs($this->anna)->get("/listings/{$this->album->id}")->assertInertia(fn ($page) => $page
            ->where('reactions.list.0.emoji', '❤️')
            ->where('reactions.list.0.count', 2)
            ->where('reactions.list.0.names', ['You', 'Muto'])
            ->where('reactions.mine', '❤️')
            ->where('reactions.canReact', true));

        // Another emoji changes it, the same one again takes it back
        $this->react($this->anna, 'album', $this->album->id, '😂');
        $this->assertSame(['😂', '❤️'], Reaction::orderBy('id')->pluck('emoji')->all());
        $this->react($this->anna, 'album', $this->album->id, '😂');
        $this->assertSame(1, Reaction::count());

        // Any emoji from the picker, never text
        $this->react($this->anna, 'album', $this->album->id, '👨‍👩‍👧')->assertSessionHasNoErrors();
        $this->react($this->anna, 'album', $this->album->id, '🇮🇹')->assertSessionHasNoErrors();
        $this->react($this->anna, 'album', $this->album->id, '👍🏽')->assertSessionHasNoErrors();
        foreach (['ok', '1', '<b>', 'é', '😂😂😂😂😂😂😂😂😂😂😂'] as $text) {
            $this->react($this->anna, 'album', $this->album->id, $text)->assertSessionHasErrors('emoji');
        }
    }

    public function test_only_who_can_see_it(): void
    {
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $demo = User::factory()->create(['is_demo' => true]);
        $this->album->users()->attach($demo->id);

        $this->react($outsider, 'album', $this->album->id, '❤️')->assertForbidden();
        // Admins who only look at an album see the reactions but can't react
        $this->react($admin, 'album', $this->album->id, '❤️')->assertForbidden();
        $this->actingAs($admin)->get("/listings/{$this->album->id}")->assertInertia(fn ($page) => $page->where('reactions.canReact', false));
        $this->react($demo, 'album', $this->album->id, '❤️')->assertForbidden();
        $this->actingAs($demo)->get("/listings/{$this->album->id}")->assertInertia(fn ($page) => $page->where('reactions.canReact', false));

        $private = Post::factory()->private()->create(['author' => $this->poster->id]);
        $this->react($this->anna, 'post', $private->id, '❤️')->assertForbidden();
        $this->react($this->anna, 'post', 999, '❤️')->assertNotFound();
        $this->assertSame(0, Reaction::count());
    }

    public function test_reactions_on_forum_posts(): void
    {
        $post = Post::factory()->create(['author' => $this->poster->id, 'title' => 'Capodanno']);

        $this->react($this->anna, 'post', $post->id, '🔥')->assertRedirect();
        $this->actingAs($this->poster)->get("/forum/{$post->category_id}/{$post->id}")->assertInertia(fn ($page) => $page
            ->where('reactions.list.0.emoji', '🔥')
            ->where('reactions.list.0.names', ['Anna'])
            ->where('reactions.mine', null)
            ->where('notifications.likes.0.kind', 'post')
            ->where('notifications.likes.0.emoji', '🔥')
            ->where('notifications.likes.0.liker', 'Anna')
            ->where('notifications.likes.0.title', 'Capodanno')
            ->where('notifications.likes.0.url', "/forum/{$post->category_id}/{$post->id}"));
    }

    public function test_the_poster_gets_the_bell_and_a_phone_notification(): void
    {
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) extends PushSender {
            public function __construct(private array &$sent) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                $this->sent[] = $payload;

                return [];
            }
        });
        $this->poster->update(['locale' => 'it']);
        PushSubscription::create(['user_id' => $this->poster->id, 'endpoint' => 'https://fcm.googleapis.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 'a']);

        $this->react($this->anna, 'album', $this->album->id, '❤️');
        $this->assertSame('Anna ha reagito ❤️ al tuo album', $sent[0]['title']);
        $this->assertSame('Sicilia', $sent[0]['body']);

        // Changed before the poster looked: the same notification, with the new emoji (no second push)
        $this->react($this->anna, 'album', $this->album->id, '😮');
        $this->assertCount(1, $sent);
        $this->assertCount(1, $this->poster->notifications);
        $this->actingAs($this->poster)->get('/')->assertInertia(fn ($page) => $page
            ->where('notifications.likes.0.emoji', '😮')
            ->where('notifications.likes.0.kind', 'album')
            ->where('notifications.likes.0.read', false));

        // Taken back: gone from the bell
        $this->react($this->anna, 'album', $this->album->id, '😮');
        $this->assertCount(0, $this->poster->fresh()->notifications);

        // Your own album: no notification
        $this->react($this->poster, 'album', $this->album->id, '❤️');
        $this->assertCount(0, $this->poster->fresh()->notifications);
        $this->assertCount(1, $sent);
    }
}
