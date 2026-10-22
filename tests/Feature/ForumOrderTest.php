<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Admins choose the order of the forum categories and of the posts of a category
class ForumOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_until_someone_reorders_the_usual_order_stays(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['title' => 'Viaggi']);
        Category::factory()->create(['title' => 'Auto']);
        $category = Category::factory()->create(['title' => 'Musica']);
        $old = Post::factory()->create(['category_id' => $category->id, 'created_at' => now()->subDay()]);
        $new = Post::factory()->create(['category_id' => $category->id]);

        $this->actingAs($user)->get('/forum')
            ->assertInertia(fn ($page) => $page->where('categories.0.title', 'Auto')->where('categories.2.title', 'Viaggi')->where('canReorder', false));
        $this->actingAs($user)->get("/forum/{$category->id}")
            ->assertInertia(fn ($page) => $page->where('posts.0.id', $new->id)->where('posts.1.id', $old->id));
    }

    public function test_admin_reorders_categories_and_new_ones_come_first(): void
    {
        $admin = User::factory()->admin()->create();
        [$a, $b, $c] = collect(['Auto', 'Musica', 'Viaggi'])->map(fn ($title) => Category::factory()->create(['title' => $title]));

        $this->actingAs($admin)->put('/forum/order', ['ids' => [$c->id, $a->id, $b->id]])->assertSessionHas('message');
        $added = Category::factory()->create(['title' => 'Zzz new']);

        $this->actingAs(User::factory()->create())->get('/forum')
            ->assertInertia(fn ($page) => $page
                ->where('categories.0.id', $added->id)
                ->where('categories.1.id', $c->id)
                ->where('categories.2.id', $a->id)
                ->where('categories.3.id', $b->id));
    }

    public function test_admin_reorders_posts_and_new_ones_come_first(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        [$first, $second, $third] = Post::factory()->count(3)->create(['category_id' => $category->id]);

        $this->actingAs($admin)->get("/forum/{$category->id}")->assertInertia(fn ($page) => $page->where('canReorder', true));
        $this->actingAs($admin)->put("/forum/{$category->id}/order", ['ids' => [$second->id, $third->id, $first->id]]);
        $added = Post::factory()->create(['category_id' => $category->id]);

        $this->assertEquals(
            [$added->id, $second->id, $third->id, $first->id],
            $category->posts()->pluck('id')->all()
        );
    }

    public function test_posts_the_admin_cant_see_keep_their_place_after_the_others(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $hidden = Post::factory()->create(['category_id' => $category->id, 'created_at' => now()->subDays(2)]);
        $hidden->hidedUser()->attach($admin);
        $a = Post::factory()->create(['category_id' => $category->id, 'created_at' => now()->subDay()]);
        $b = Post::factory()->create(['category_id' => $category->id]);
        // Another category's post can't be moved in here
        $elsewhere = Post::factory()->create();

        $this->actingAs($admin)->put("/forum/{$category->id}/order", ['ids' => [$a->id, $elsewhere->id, $b->id]]);

        $this->assertEquals([$a->id, $b->id, $hidden->id], $category->posts()->pluck('id')->all());
        $this->assertNull($elsewhere->fresh()->position);
    }

    public function test_only_admins_reorder(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create(['category_id' => $category->id, 'author' => $user->id]);

        $this->actingAs($user)->put('/forum/order', ['ids' => [$category->id]])->assertForbidden();
        $this->actingAs($user)->put("/forum/{$category->id}/order", ['ids' => [$post->id]])->assertForbidden();
        $this->assertNull($category->fresh()->position);
        $this->assertNull($post->fresh()->position);
    }
}
