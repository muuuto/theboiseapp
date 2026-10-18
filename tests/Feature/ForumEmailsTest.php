<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// No Mail::fake() here: the forum sends mails with Mail::send() and a view, which the fake ignores,
// so we read what the "array" mailer (set in phpunit.xml) really sent
class ForumEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_gets_one_email_when_someone_comments(): void
    {
        $post = Post::factory()->create();
        $author = User::find($post->author);
        $friend = User::factory()->create(['name' => 'Zecca']);

        $this->comment($friend, $post);

        $this->assertEquals([$author->email], $this->recipients());
        $this->assertStringContainsString('Zecca', $this->sentMessages()->first()->toString());
    }

    public function test_author_gets_no_email_for_their_own_comment(): void
    {
        $post = Post::factory()->create();
        $friend = User::factory()->create();
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $friend->id]);

        $this->comment(User::find($post->author), $post);

        $this->assertEquals([$friend->email], $this->recipients());
    }

    public function test_everyone_who_commented_gets_exactly_one_email(): void
    {
        $post = Post::factory()->create();
        $first = User::factory()->create();
        $chatty = User::factory()->create();
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $first->id]);
        PostComment::factory()->count(3)->create(['post_id' => $post->id, 'user_id' => $chatty->id]);

        $this->comment(User::factory()->create(), $post);

        $this->assertEqualsCanonicalizing(
            [User::find($post->author)->email, $first->email, $chatty->email],
            $this->recipients()
        );
    }

    public function test_users_the_post_is_hidden_from_get_no_email(): void
    {
        $post = Post::factory()->create();
        $hidden = User::factory()->create();
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $hidden->id]);
        $post->hidedUser()->attach($hidden);

        $this->comment(User::factory()->create(), $post);

        $this->assertEquals([User::find($post->author)->email], $this->recipients());
    }

    public function test_notify_emails_everyone_who_can_see_the_post(): void
    {
        $post = Post::factory()->create();
        $visible = User::factory()->create();
        $hidden = User::factory()->create();
        $post->hidedUser()->attach($hidden);

        $this->actingAs(User::find($post->author))
            ->post("/forum/{$post->category_id}/{$post->id}/notify")
            ->assertRedirect();

        // Everyone except the author and the hidden user (the category creator included)
        $expected = User::whereNotIn('id', [$post->author, $hidden->id])->pluck('email')->all();
        $this->assertContains($visible->email, $expected);
        $this->assertEqualsCanonicalizing($expected, $this->recipients());
    }

    private function comment(User $user, Post $post): void
    {
        $this->actingAs($user)
            ->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Bella!'])
            ->assertRedirect();
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    private function recipients(): array
    {
        return $this->sentMessages()
            ->flatMap(fn ($message) => $message->getEnvelope()->getRecipients())
            ->map(fn ($address) => $address->getAddress())
            ->all();
    }
}
