<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Listing;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Mentioned;
use App\Support\Mentions;
use App\Support\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// "@Anna B" in a comment: bold, and Anna is told (only people who can see it)
class MentionsTest extends TestCase
{
    use RefreshDatabase;

    private array $pushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $pushed = &$this->pushed;
        $this->app->instance(PushSender::class, new class($pushed) extends PushSender {
            public function __construct(private array &$pushed) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                foreach ($subscriptions as $subscription) {
                    $this->pushed[$subscription->user_id][] = $payload['title'];
                }

                return [];
            }
        });
    }

    private function phone(User ...$users): void
    {
        foreach ($users as $user) {
            PushSubscription::create(['user_id' => $user->id, 'endpoint' => "https://fcm.googleapis.com/{$user->id}", 'endpoint_hash' => hash('sha256', (string) $user->id), 'public_key' => 'k', 'auth_token' => 'a']);
        }
    }

    public function test_names_are_found_longest_first(): void
    {
        $people = collect([new User(['name' => 'Anna']), new User(['name' => 'Anna B']), new User(['name' => 'Bob'])]);

        $this->assertSame(['Anna B'], Mentions::find('ciao @Anna B!', $people)->pluck('name')->all());
        $this->assertSame(['Anna B', 'Anna'], Mentions::find('@anna b e @Anna', $people)->pluck('name')->all());
        $this->assertSame([], Mentions::find('mail@Bob.it e @Bobby', $people)->pluck('name')->all());
    }

    public function test_mentioned_in_an_album_comment(): void
    {
        [$me, $anna, $bob] = [User::factory()->create(['name' => 'Muto']), User::factory()->create(['name' => 'Anna B']), User::factory()->create(['name' => 'Bob'])];
        $outsider = User::factory()->create(['name' => 'Zecca']);
        $album = Listing::factory()->create(['title' => 'Sicilia']);
        $album->users()->attach([$me->id, $anna->id, $bob->id]);
        $this->phone($anna, $bob, $outsider);

        $this->actingAs($me)->get("/listings/{$album->id}")->assertInertia(fn ($page) => $page->where('mentionable', fn ($people) => collect($people)->pluck('name')->sort()->values()->all() === ['Anna B', 'Bob']));
        $this->actingAs($me)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => '@Anna B guarda la foto 12! (e @Zecca)'])->assertSessionHasNoErrors();

        $this->assertStringContainsString('<strong>@Anna B</strong> guarda', Comment::sole()->comment);
        // Anna: "mentioned you" instead of "commented"; Bob: the usual one; Zecca isn't in the album
        $this->assertSame(['Muto mentioned you'], $this->pushed[$anna->id]);
        $this->assertSame(['Muto commented on Sicilia'], $this->pushed[$bob->id]);
        $this->assertArrayNotHasKey($outsider->id, $this->pushed);
        $this->actingAs($anna)->get('/')->assertInertia(fn ($page) => $page
            ->where('notifications.likes.0.kind', 'mention')
            ->where('notifications.likes.0.liker', 'Muto')
            ->where('notifications.likes.0.title', 'Sicilia'));
    }

    public function test_mentioned_under_a_forum_post_only_if_they_can_see_it(): void
    {
        [$me, $anna] = [User::factory()->create(['name' => 'Muto']), User::factory()->create(['name' => 'Anna'])];
        $this->phone($anna);
        $post = Post::factory()->create(['author' => $me->id, 'title' => 'Capodanno']);

        $this->actingAs($me)->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Che ne dici @Anna?'])->assertSessionHasNoErrors();
        $this->assertSame(['Muto mentioned you'], $this->pushed[$anna->id]);
        $this->actingAs($me)->get("/forum/{$post->category_id}/{$post->id}")->assertInertia(fn ($page) => $page->where('comments.0.mentions', ['Anna']));

        // A private post: Anna can't see it, so she isn't told
        $private = Post::factory()->private()->create(['author' => $me->id, 'category_id' => $post->category_id]);
        $this->actingAs($me)->post("/forum/{$private->category_id}/{$private->id}/comment", ['comment' => 'Ciao @Anna']);
        $this->assertCount(1, $this->pushed[$anna->id]);
        $this->assertSame(1, $anna->notifications()->where('type', Mentioned::class)->count());
    }
}
