<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Post;
use App\Models\Slogan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->demo = User::factory()->create(['name' => 'Guest', 'is_demo' => true]);
        config(['app.demo_secret' => 'the-cv-secret']);
    }

    public function test_the_secret_link_logs_into_the_demo_account(): void
    {
        $this->get('/demo/the-cv-secret')->assertRedirect('/');

        $this->assertAuthenticatedAs($this->demo);
    }

    public function test_a_wrong_secret_does_nothing(): void
    {
        $this->get('/demo/wrong')->assertNotFound();

        $this->assertGuest();
    }

    public function test_the_demo_link_is_disabled_without_a_secret(): void
    {
        config(['app.demo_secret' => null]);

        $this->get('/demo/anything')->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_sees_only_its_albums_and_no_slogans(): void
    {
        Slogan::create(['sloganPhrase' => 'Zecca joke', 'author' => 'Zecca']);
        Listing::factory()->create(['title' => 'Nice trip'])->users()->attach($this->demo);
        Listing::factory()->create(['title' => 'Silly party']);

        $this->actingAs($this->demo)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('slogan', null)
                ->where('auth.user.isDemo', true)
                ->where('albums.total', 1)
                ->where('albums.data.0.title', 'Nice trip'))
            ->assertDontSee('Zecca joke')
            ->assertDontSee('albumses');
    }

    public function test_demo_cannot_open_the_forum(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($this->demo)->get('/forum')->assertForbidden();
        $this->actingAs($this->demo)->get("/forum/{$post->category_id}")->assertForbidden();
        $this->actingAs($this->demo)->get("/forum/{$post->category_id}/{$post->id}")->assertForbidden();
    }

    public function test_demo_is_read_only(): void
    {
        Mail::fake();
        $listing = Listing::factory()->create();
        $listing->users()->attach($this->demo);

        $this->actingAs($this->demo)->get('/slogan/create')->assertForbidden();
        $this->actingAs($this->demo)->post('/slogan', ['sloganPhrase' => 'x'])->assertForbidden();
        $this->actingAs($this->demo)->post('/listings/comments', ['listing_id' => $listing->id, 'comment' => 'hi'])->assertForbidden();
        $this->actingAs($this->demo)->put('/account/update', ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($this->demo)->put('/account/theme', ['theme' => 'dark'])->assertForbidden();

        $this->assertEquals('Guest', $this->demo->fresh()->name);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_demo_can_still_open_its_albums(): void
    {
        $listing = Listing::factory()->create();
        $listing->users()->attach($this->demo);

        $this->actingAs($this->demo)->get("/listings/{$listing->id}")->assertOk();
        $this->actingAs($this->demo)->get('/account')->assertOk();
    }

    public function test_demo_never_receives_emails(): void
    {
        // Real "array" mailer, not Mail::fake(): the block happens when a mail is about to be sent
        $friend = User::factory()->create();
        Mail::raw('Hello', fn ($message) => $message->to($this->demo->email));
        Mail::raw('Hello', fn ($message) => $message->to($friend->email));

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertEquals($friend->email, $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_demo_is_not_a_forum_member_in_notification_emails(): void
    {
        // Comment emails go to everyone who commented: the demo account is skipped by the mail block
        $post = Post::factory()->create();
        Category::find($post->category_id);
        $this->actingAs(User::factory()->create())
            ->post("/forum/{$post->category_id}/{$post->id}/comment", ['comment' => 'Ciao']);

        $recipients = app('mailer')->getSymfonyTransport()->messages()
            ->flatMap(fn ($m) => $m->getEnvelope()->getRecipients())->map->getAddress();
        $this->assertNotContains($this->demo->email, $recipients);
    }
}
