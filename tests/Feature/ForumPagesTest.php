<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForumPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');
    }

    public function test_forum_home_counts_only_the_posts_you_can_see(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['title' => 'Ricette', 'created_by' => $user->id]);
        Post::factory()->create(['category_id' => $category->id]);
        Post::factory()->private()->create(['category_id' => $category->id]);
        Post::factory()->create(['category_id' => $category->id])->hidedUser()->attach($user);

        $this->actingAs($user)->get('/forum')
            ->assertInertia(fn ($page) => $page
                ->component('Forum/Index')
                ->where('categories.0.title', 'Ricette')
                ->where('categories.0.postCount', 1)
                ->where('categories.0.canEdit', true)
                ->where('categories.0.canDelete', false));
    }

    public function test_category_page_lists_visible_posts_with_permissions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $mine = Post::factory()->create(['category_id' => $category->id, 'author' => $user->id, 'title' => 'Mine']);
        Post::factory()->create(['category_id' => $category->id, 'title' => 'Shared', 'canEdit' => 1]);
        PostComment::factory()->count(2)->create(['post_id' => $mine->id]);

        $this->actingAs($user)->get("/forum/{$category->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Forum/Category')
                ->has('posts', 2)
                ->where('posts', fn ($posts) => collect($posts)->firstWhere('title', 'Mine')['canDelete'] === true
                    && collect($posts)->firstWhere('title', 'Mine')['comments'] === 2
                    && collect($posts)->firstWhere('title', 'Shared')['canEdit'] === true
                    && collect($posts)->firstWhere('title', 'Shared')['canDelete'] === false));
    }

    public function test_post_page_splits_photos_and_documents_and_shows_plain_comments(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'attachments' => json_encode(['attachments/a.webp', 'attachments/b.pdf', 'attachments/c.webp']),
        ]);
        PostComment::factory()->create(['post_id' => $post->id, 'comment' => "Bello\n<b>davvero</b>"]);

        $this->actingAs($user)->get("/forum/{$post->category_id}/{$post->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Forum/Post')
                ->has('post.images', 2)
                ->has('post.pdfs', 1)
                ->where('post.pdfs.0.name', 'Attachment 1')
                // Plain text, shown as text by the page (no " XD" anymore)
                ->where('comments.0.text', "Bello\n<b>davvero</b>")
                ->where('can.delete', false)
                ->where('can.notify', false));
    }

    public function test_attachments_can_be_kept_removed_and_added_one_by_one(): void
    {
        Storage::disk('public')->put('attachments/keep.pdf', 'x');
        Storage::disk('public')->put('attachments/drop.pdf', 'x');
        $post = Post::factory()->create(['attachments' => json_encode(['attachments/keep.pdf', 'attachments/drop.pdf'])]);

        $this->actingAs(User::find($post->author))->put("/forum/{$post->category_id}/{$post->id}/update", [
            'title' => 'Title',
            'description' => 'Description',
            'tags' => ['general'],
            'content' => '<p>Content</p>',
            'keepAttachments' => ['attachments/keep.pdf'],
            'attachments' => [UploadedFile::fake()->image('new.png', 800, 600)],
        ])->assertRedirect();

        $attachments = json_decode($post->fresh()->attachments, true);
        $this->assertCount(2, $attachments);
        $this->assertEquals('attachments/keep.pdf', $attachments[0]);
        $this->assertStringEndsWith('.webp', $attachments[1]);
        Storage::disk('public')->assertMissing('attachments/drop.pdf');
    }

    public function test_webp_images_can_be_uploaded(): void
    {
        $user = User::factory()->create();
        $webp = $this->webp();

        $this->actingAs($user)->post('/forum/category/store', [
            'title' => 'Webp category',
            'description' => 'Description',
            'tags' => ['general'],
            'logo' => $webp,
        ])->assertRedirect();

        $category = Category::where('title', 'Webp category')->firstOrFail();
        Storage::disk('public')->assertExists($category->logo);
    }

    public function test_old_manage_pages_redirect(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)->get('/forum/category/manage')->assertRedirect('/forum');
        $this->actingAs($user)->get("/forum/{$category->id}/manage")->assertRedirect("/forum/{$category->id}");
    }

    public function test_hidden_category_cannot_receive_new_posts(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $category->hidedUser()->attach($user);

        $this->actingAs($user)->get("/forum/{$category->id}/create")->assertForbidden();
        $this->actingAs($user)->post("/forum/{$category->id}/store", [
            'title' => 'x', 'description' => 'x', 'tags' => ['x'], 'content' => 'x',
        ])->assertForbidden();
    }

    private function webp(): UploadedFile
    {
        $image = imagecreatetruecolor(300, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 150, 120));
        $file = tempnam(sys_get_temp_dir(), 'logo') . '.webp';
        imagewebp($image, $file);

        return new UploadedFile($file, 'logo.webp', 'image/webp', null, true);
    }
}
