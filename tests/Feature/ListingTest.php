<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
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
            'tags' => 'summer',
            'description' => 'Holidays',
            'people' => $friends->pluck('id')->all(),
        ])->assertRedirect('/');

        $listing = Listing::where('title', 'Summer 2026')->firstOrFail();
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
            'comment' => 'Che bei ricordi 😍',
        ])->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'comment' => 'Che bei ricordi 😍',
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
}
