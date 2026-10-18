<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\Slogan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_home_only_shows_albums_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $mine = Listing::factory()->create(['title' => 'Trip to Merano']);
        $other = Listing::factory()->create(['title' => 'Someone else trip']);
        $mine->users()->attach($user);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Trip to Merano')
            ->assertDontSee('Someone else trip');
    }

    public function test_member_can_view_album_and_it_is_marked_as_seen(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $listing->users()->attach($user);

        $this->actingAs($user)->get("/listings/{$listing->id}")->assertOk();

        $this->assertDatabaseHas('listing_user', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'seen' => 1,
        ]);
    }

    public function test_non_member_cannot_view_album(): void
    {
        $listing = Listing::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/listings/{$listing->id}")
            ->assertForbidden();
    }

    public function test_only_admins_can_open_the_create_form(): void
    {
        $this->actingAs(User::factory()->create())->get('/listings/create')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/listings/create')->assertOk();
    }

    public function test_admin_can_create_album_for_people(): void
    {
        $admin = User::factory()->admin()->create();
        $friends = User::factory()->count(2)->create();

        $this->actingAs($admin)->post('/listings', [
            'title' => 'Summer 2026',
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-05',
            'location' => 'Merano',
            'albumLink' => 'https://www.amazon.com/photos/shared/abc',
            'tags' => ['summer'],
            'description' => 'Holidays',
            'people' => $friends->pluck('id')->all(),
        ]);

        $listing = Listing::where('title', 'Summer 2026')->firstOrFail();
        $this->assertEquals('summer', $listing->tags);
        $this->assertEquals($admin->id, $listing->user_id);
        $this->assertEqualsCanonicalizing($friends->pluck('id')->all(), $listing->users->pluck('id')->all());
    }

    public function test_member_can_comment_on_album(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $listing->users()->attach([$user->id, User::factory()->create()->id]);

        $this->actingAs($user)->post('/listings/comments', [
            'listing_id' => $listing->id,
            'comment' => "Che bei ricordi 😍\n<script>x</script>",
        ])->assertRedirect();

        // Plain text: kept as text (never as HTML), line breaks kept
        $this->assertDatabaseHas('comments', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'comment' => '<p>Che bei ricordi 😍<br>' . "\n" . '&lt;script&gt;x&lt;/script&gt;</p>',
        ]);
    }

    public function test_album_comments_are_shown(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $listing->users()->attach($user);
        Comment::factory()->create(['listing_id' => $listing->id, 'user_id' => $user->id, 'comment' => 'First!']);

        $this->actingAs($user)->get("/listings/{$listing->id}")->assertSee('First!');
    }

    public function test_scripts_in_album_comments_and_description_are_removed(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['description' => '<p>Gita</p><script>alert("description")</script>']);
        $listing->users()->attach($user);
        Comment::factory()->create([
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'comment' => '<b>Bello</b> <3<img src=x onerror="alert(\'comment\')">',
        ]);

        $this->actingAs($user)->get("/listings/{$listing->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Show')
                ->where('album.description', '<p>Gita</p>')
                ->where('comments.0.html', '<b>Bello</b> &lt;3<img />'));
    }

    public function test_slogans_keep_links_but_lose_scripts(): void
    {
        $user = User::factory()->create();
        Slogan::create([
            'sloganPhrase' => 'check <a href="https://example.com">here</a><script>alert("slogan")</script>',
            'author' => 'Zecca',
        ]);

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Index')
                ->where('slogan.html', 'check <a href="https://example.com" rel="noopener noreferrer">here</a>')
                ->where('slogan.author', 'Zecca'));
    }
}
