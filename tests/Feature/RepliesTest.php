<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// Replies to comments, one level deep: under the comment, and who's answered is told
class RepliesTest extends TestCase
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

    public function test_replies_in_an_album(): void
    {
        [$me, $anna, $bob] = [User::factory()->create(['name' => 'Muto']), User::factory()->create(['name' => 'Anna']), User::factory()->create(['name' => 'Bob'])];
        $album = Listing::factory()->create(['title' => 'Sicilia']);
        $album->users()->attach([$me->id, $anna->id, $bob->id]);
        $this->phone($me, $anna, $bob);

        $this->actingAs($anna)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => 'Che mare!']);
        $first = Comment::sole();
        $this->pushed = [];

        // Muto answers Anna: Anna "replied to your comment", Bob the usual one
        $this->actingAs($me)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => 'Vero!', 'parent_id' => $first->id])->assertSessionHasNoErrors();
        $reply = Comment::latest('id')->first();
        $this->assertSame($first->id, $reply->parent_id);
        $this->assertSame(['Muto replied to your comment'], $this->pushed[$anna->id]);
        $this->assertSame(['Muto commented on Sicilia'], $this->pushed[$bob->id]);
        $this->actingAs($anna)->get('/')->assertInertia(fn ($page) => $page->where('notifications.likes.0.kind', 'reply'));

        // Bob answers Muto's reply: same thread (one level), Muto is the one told
        $this->pushed = [];
        $this->actingAs($bob)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => '@Muto anche io', 'parent_id' => $reply->id]);
        $this->assertSame($first->id, Comment::latest('id')->first()->parent_id);
        $this->assertSame(['Bob replied to your comment'], $this->pushed[$me->id]);
        $this->assertSame(['Bob commented on Sicilia'], $this->pushed[$anna->id]);

        $this->actingAs($me)->get("/listings/{$album->id}")->assertInertia(fn ($page) => $page
            ->has('comments', 1)
            ->where('comments.0.id', $first->id)
            ->has('comments.0.replies', 2)
            ->where('comments.0.replies.0.author', 'Muto')
            ->where('comments.0.replies.1.author', 'Bob'));

        // A comment of another album can't be the one answered: it's a new comment
        $other = Listing::factory()->create();
        $other->users()->attach($me->id);
        $this->actingAs($me)->post('/listings/comments', ['listing_id' => $other->id, 'comment' => 'x', 'parent_id' => $first->id]);
        $this->assertNull(Comment::latest('id')->first()->parent_id);
    }

    public function test_replies_under_a_forum_post(): void
    {
        [$me, $anna] = [User::factory()->create(['name' => 'Muto']), User::factory()->create(['name' => 'Anna'])];
        $this->phone($anna);
        $post = Post::factory()->create(['author' => $me->id]);
        $this->actingAs($anna)->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Ci sono!']);
        $first = PostComment::sole();

        $this->actingAs($me)->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Grande', 'parent_id' => $first->id])->assertSessionHasNoErrors();
        $this->assertSame($first->id, PostComment::latest('id')->first()->parent_id);
        $this->assertSame(['Muto replied to your comment'], $this->pushed[$anna->id]);
        $this->actingAs($me)->get("/forum/{$post->category_id}/{$post->id}")->assertInertia(fn ($page) => $page
            ->has('comments', 1)
            ->where('comments.0.replies.0.text', 'Grande'));
    }
}
