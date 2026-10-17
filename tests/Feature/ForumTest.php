<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_hidden_categories_are_not_listed(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['title' => 'Visible category']);
        $hidden = Category::factory()->create(['title' => 'Secret category']);
        $hidden->hidedUser()->attach($user);

        $this->actingAs($user)->get('/forum')
            ->assertOk()
            ->assertSee('Visible category')
            ->assertDontSee('Secret category');
    }

    public function test_hidden_category_cannot_be_opened(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $category->hidedUser()->attach($user);

        $this->actingAs($user)->get("/forum/{$category->id}")->assertForbidden();
    }

    public function test_hidden_posts_are_not_listed(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Post::factory()->create(['category_id' => $category->id, 'title' => 'Visible post']);
        $hidden = Post::factory()->create(['category_id' => $category->id, 'title' => 'Secret post']);
        $hidden->hidedUser()->attach($user);

        $this->actingAs($user)->get("/forum/{$category->id}")
            ->assertOk()
            ->assertSee('Visible post')
            ->assertDontSee('Secret post');
    }

    public function test_viewing_a_post_increments_its_counter(): void
    {
        $post = Post::factory()->create(['title' => 'Hello forum']);

        $this->actingAs(User::factory()->create())
            ->get("/forum/{$post->category_id}/{$post->id}")
            ->assertOk()
            ->assertSee('Hello forum');

        $this->assertEquals(1, $post->fresh()->counter);
    }

    public function test_hidden_post_cannot_be_opened(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $post->hidedUser()->attach($user);

        $this->actingAs($user)->get("/forum/{$post->category_id}/{$post->id}")->assertForbidden();
    }

    public function test_private_post_is_only_visible_to_its_author(): void
    {
        $post = Post::factory()->private()->create();

        $this->actingAs(User::factory()->create())
            ->get("/forum/{$post->category_id}/{$post->id}")
            ->assertForbidden();

        $this->actingAs(User::find($post->author))
            ->get("/forum/{$post->category_id}/{$post->id}")
            ->assertOk();
    }

    public function test_user_can_create_post(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)->post("/forum/{$category->id}/store", [
            'title' => 'New recipe',
            'description' => 'Tiramisù',
            'tags' => 'dessert',
            'content' => 'Mascarpone, eggs, coffee',
        ])->assertRedirect("/forum/{$category->id}");

        $this->assertDatabaseHas('posts', [
            'title' => 'New recipe',
            'author' => $user->id,
            'category_id' => $category->id,
            'is_private' => 0,
        ]);
    }

    public function test_user_can_comment_on_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Nice 👍'])
            ->assertRedirect();

        $this->assertDatabaseHas('post_comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'comment' => 'Nice 👍',
        ]);
    }
}
