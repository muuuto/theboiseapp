<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $friend;
    private Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => 'Anna']);
        $this->friend = User::factory()->create(['name' => 'Zecca']);
        $listing = Listing::factory()->create(['title' => 'Lago di Favogna']);
        $listing->users()->attach([$this->author->id, $this->friend->id]);
        $this->comment = Comment::factory()->create([
            'listing_id' => $listing->id,
            'user_id' => $this->author->id,
            'comment' => '<p>Che bella giornata!</p>',
        ]);
    }

    public function test_the_author_finds_the_like_in_the_bell(): void
    {
        $this->like($this->friend);

        $this->actingAs($this->author)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('notifications.likes.0.liker', 'Zecca')
                ->where('notifications.likes.0.excerpt', 'Che bella giornata!')
                ->where('notifications.likes.0.title', 'Lago di Favogna')
                ->where('notifications.likes.0.url', "/listings/{$this->comment->listing_id}#comment-{$this->comment->id}")
                ->where('notifications.likes.0.read', false));
    }

    public function test_no_notification_for_liking_your_own_comment(): void
    {
        $this->like($this->author);

        $this->assertCount(0, $this->author->notifications);
    }

    public function test_the_demo_account_gets_no_notifications(): void
    {
        $this->author->update(['is_demo' => true]);

        $this->like($this->friend);

        $this->assertCount(0, $this->author->fresh()->notifications);
    }

    public function test_unliking_before_it_was_seen_removes_the_notification(): void
    {
        $this->like($this->friend);
        $this->like($this->friend);

        $this->assertCount(0, $this->author->fresh()->notifications);
    }

    public function test_unliking_after_it_was_seen_keeps_it_in_the_history(): void
    {
        $this->like($this->friend);
        $this->author->unreadNotifications->markAsRead();
        $this->like($this->friend);

        $this->assertCount(1, $this->author->fresh()->notifications);
    }

    public function test_opening_the_bell_marks_likes_as_read(): void
    {
        $this->like($this->friend);

        // From a Vue page (Inertia) and from an old Blade page (plain request)
        $this->actingAs($this->author)->post('/notifications/read', [], ['X-Inertia' => 'true'])->assertRedirect();
        $this->assertCount(0, $this->author->fresh()->unreadNotifications);

        $this->like($this->friend);
        $this->like($this->friend);
        $this->like($this->friend);
        $this->actingAs($this->author)->post('/notifications/read')->assertNoContent();
        $this->assertCount(0, $this->author->fresh()->unreadNotifications);
    }

    public function test_clicking_a_like_in_the_bell_removes_it_and_opens_the_comment(): void
    {
        $this->like($this->friend);
        $notification = $this->author->notifications->first();

        $this->actingAs($this->author)->get("/notifications/{$notification->id}")
            ->assertRedirect("/listings/{$this->comment->listing_id}")
            // the #comment-12 of the bell's link stays
            ->assertSessionHas('inertia.preserve_fragment', true);

        $this->assertCount(0, $this->author->fresh()->notifications);
        $this->actingAs($this->author)->get('/')
            ->assertInertia(fn ($page) => $page->where('notifications.likes', []));
    }

    public function test_a_like_already_opened_or_of_someone_else_goes_home(): void
    {
        $this->like($this->friend);
        $notification = $this->author->notifications->first();

        // Someone else can't open (and remove) it
        $this->actingAs($this->friend)->get("/notifications/{$notification->id}")->assertRedirect('/');
        $this->assertCount(1, $this->author->fresh()->notifications);

        $this->actingAs($this->author)->get("/notifications/{$notification->id}");
        $this->actingAs($this->author)->get("/notifications/{$notification->id}")->assertRedirect('/');
    }

    public function test_the_bell_shows_likes_on_every_page(): void
    {
        $this->like($this->friend);

        $this->actingAs($this->author)->get('/account')
            ->assertInertia(fn ($page) => $page->component('Account/Index')->where('notifications.likes.0.liker', 'Zecca'));
    }

    public function test_forum_comment_likes_link_to_the_post(): void
    {
        $post = Post::factory()->create();
        $comment = PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->author->id]);

        $this->actingAs($this->friend)->post('/likes', ['type' => 'post_comment', 'id' => $comment->id]);

        $this->assertEquals(
            "/forum/{$post->category_id}/{$post->id}#comment-{$comment->id}",
            $this->author->fresh()->notifications->first()->data['url']
        );
    }

    private function like(User $user): void
    {
        $this->actingAs($user)->post('/likes', ['type' => 'album_comment', 'id' => $this->comment->id]);
    }
}
