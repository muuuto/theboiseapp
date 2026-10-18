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
            'tags' => ['dessert'],
            'content' => 'Mascarpone, eggs, coffee',
        ])->assertRedirect();

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

    public function test_private_posts_of_others_are_not_listed(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        Post::factory()->private()->create(['category_id' => $category->id, 'title' => 'Someone else private']);
        Post::factory()->private()->create(['category_id' => $category->id, 'author' => $user->id, 'title' => 'My private']);

        $this->actingAs($user)->get("/forum/{$category->id}")
            ->assertSee('My private')
            ->assertDontSee('Someone else private');
    }

    public function test_post_without_attachments_can_be_deleted(): void
    {
        $post = Post::factory()->create(['attachments' => null]);

        $this->actingAs(User::find($post->author))
            ->delete("/forum/{$post->category_id}/{$post->id}/delete")
            ->assertRedirect("/forum/{$post->category_id}");

        $this->assertModelMissing($post);
    }

    public function test_category_whose_posts_have_no_attachments_can_be_deleted(): void
    {
        $category = Category::factory()->create();
        Post::factory()->create(['category_id' => $category->id, 'attachments' => null]);

        $this->actingAs($this->admin())
            ->delete("/forum/{$category->id}")
            ->assertRedirect('/forum');

        $this->assertModelMissing($category);
    }

    public function test_author_can_change_private_and_can_edit(): void
    {
        $post = Post::factory()->create();
        $author = User::find($post->author);

        $this->actingAs($author)->put($this->updateUrl($post), $this->postFields(['is_private' => '1', 'canEdit' => '1']));
        $this->assertEquals([1, 1], [$post->fresh()->is_private, $post->fresh()->canEdit]);

        $this->actingAs($author)->put($this->updateUrl($post), $this->postFields());
        $this->assertEquals([0, 0], [$post->fresh()->is_private, $post->fresh()->canEdit]);
    }

    public function test_edit_form_shows_the_current_privacy_settings(): void
    {
        $post = Post::factory()->create(['is_private' => 1, 'canEdit' => 0]);

        $this->actingAs(User::find($post->author))
            ->get("/forum/{$post->category_id}/{$post->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->component('Forum/PostForm')
                ->where('post.isPrivate', true)
                ->where('post.canEdit', false)
                ->where('canManage', true));
    }

    public function test_others_allowed_to_edit_can_only_change_the_content(): void
    {
        $hiddenFrom = User::factory()->create();
        $post = Post::factory()->create(['canEdit' => 1]);
        $post->hidedUser()->attach($hiddenFrom);
        $friend = User::factory()->create();

        $this->actingAs($friend)->get("/forum/{$post->category_id}/{$post->id}/edit")
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($friend)->put($this->updateUrl($post), $this->postFields(['title' => 'Edited by a friend', 'is_private' => '1']));

        $post->refresh();
        $this->assertEquals('Edited by a friend', $post->title);
        $this->assertEquals(0, $post->is_private);
        $this->assertEquals(1, $post->canEdit);
        $this->assertTrue($post->hidedUser->contains($hiddenFrom));
    }

    public function test_unticking_everyone_unhides_the_post(): void
    {
        $post = Post::factory()->create();
        $post->hidedUser()->attach(User::factory()->create());

        $this->actingAs(User::find($post->author))->put($this->updateUrl($post), $this->postFields());

        $this->assertCount(0, $post->fresh()->hidedUser);
    }

    public function test_unticking_everyone_unhides_the_category(): void
    {
        $category = Category::factory()->create();
        $category->hidedUser()->attach(User::factory()->create());

        $this->actingAs(User::find($category->created_by))->put("/forum/{$category->id}", $this->categoryFields());

        $this->assertCount(0, $category->fresh()->hidedUser);
    }

    public function test_category_can_be_edited_by_its_creator_and_admins_only(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::find($category->created_by))
            ->put("/forum/{$category->id}", $this->categoryFields(['title' => 'By creator']))
            ->assertRedirect("/forum/{$category->id}");
        $this->assertEquals('By creator', $category->fresh()->title);

        $this->actingAs($this->admin())
            ->put("/forum/{$category->id}", $this->categoryFields(['title' => 'By admin']))
            ->assertRedirect("/forum/{$category->id}");
        $this->assertEquals('By admin', $category->fresh()->title);

        $this->actingAs(User::factory()->create())
            ->put("/forum/{$category->id}", $this->categoryFields(['title' => 'By someone else']))
            ->assertForbidden();
        $this->assertEquals('By admin', $category->fresh()->title);
    }

    public function test_notify_is_only_for_the_author_and_admins(): void
    {
        $post = Post::factory()->create();
        $url = "/forum/{$post->category_id}/{$post->id}/notify";

        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        $this->actingAs(User::find($post->author))->get($url)->assertMethodNotAllowed();
        $this->actingAs(User::find($post->author))->post($url)->assertRedirect();
        $this->actingAs($this->admin())->post($url)->assertRedirect();
    }

    public function test_scripts_in_post_content_are_removed(): void
    {
        $post = Post::factory()->create(['content' => '<p>Ciao <strong>tutti</strong></p><script>alert("x")</script>']);

        $this->actingAs(User::find($post->author))->get("/forum/{$post->category_id}/{$post->id}")
            ->assertInertia(fn ($page) => $page->where('post.content', '<p>Ciao <strong>tutti</strong></p>'));
    }

    public function test_splitwise_is_gone(): void
    {
        $this->actingAs(User::factory()->create())->get('/splitwise')->assertNotFound();
    }

    // An admin whose id is not 1, to make sure nothing checks "user #1" anymore
    private function admin(): User
    {
        User::factory()->create();

        return User::factory()->admin()->create();
    }

    private function updateUrl(Post $post): string
    {
        return "/forum/{$post->category_id}/{$post->id}/update";
    }

    private function postFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Title',
            'description' => 'Description',
            'tags' => ['general'],
            'content' => 'Content',
        ], $overrides);
    }

    private function categoryFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Category',
            'tags' => ['general'],
            'description' => 'Description',
        ], $overrides);
    }
}
