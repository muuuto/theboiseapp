<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlbumFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_album_page_has_details_people_and_comments_with_likes(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);
        $friend = User::factory()->create(['name' => 'Zecca']);
        $listing = Listing::factory()->create([
            'videoLinks' => 'https://youtu.be/dQw4w9WgXcQ, https://vimeo.com/1',
            'latitude' => 46.67,
            'longitude' => 11.16,
        ]);
        $listing->users()->attach([$user->id, $friend->id]);
        $comment = Comment::factory()->create(['listing_id' => $listing->id, 'user_id' => $friend->id, 'comment' => '<p>Bello</p>']);
        $comment->likes()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/listings/{$listing->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Show')
                ->where('album.title', $listing->title)
                ->where('album.videoLinks', ['https://youtu.be/dQw4w9WgXcQ', 'https://vimeo.com/1'])
                ->where('album.latitude', 46.67)
                ->has('album.people', 2)
                ->where('comments.0.author', 'Zecca')
                ->where('comments.0.likes.count', 1)
                ->where('comments.0.likes.liked', true)
                ->where('comments.0.likes.names', ['Anna'])
                ->where('can.comment', true)
                ->where('can.manage', false));
    }

    public function test_only_people_in_the_album_can_comment(): void
    {
        $listing = Listing::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post('/listings/comments', ['listing_id' => $listing->id, 'comment' => 'Hi'])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_like_toggles_on_and_off(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $listing->users()->attach($user);
        $comment = Comment::factory()->create(['listing_id' => $listing->id]);

        $this->actingAs($user)->post('/likes', ['type' => 'album_comment', 'id' => $comment->id])->assertRedirect();
        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'likeable_type' => 'album_comment', 'likeable_id' => $comment->id]);

        $this->actingAs($user)->post('/likes', ['type' => 'album_comment', 'id' => $comment->id]);
        $this->assertDatabaseCount('likes', 0);
    }

    public function test_cannot_like_comments_of_albums_you_are_not_in(): void
    {
        $comment = Comment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post('/likes', ['type' => 'album_comment', 'id' => $comment->id])
            ->assertForbidden();
    }

    public function test_demo_cannot_like(): void
    {
        $demo = User::factory()->create(['is_demo' => true]);
        $listing = Listing::factory()->create();
        $listing->users()->attach($demo);
        $comment = Comment::factory()->create(['listing_id' => $listing->id]);

        $this->actingAs($demo)->post('/likes', ['type' => 'album_comment', 'id' => $comment->id])->assertForbidden();
        $this->actingAs($demo)->get("/listings/{$listing->id}")
            ->assertInertia(fn ($page) => $page->where('can.comment', false));
    }

    public function test_admin_creates_an_album_with_cover_place_and_videos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $friend = User::factory()->create();

        $this->actingAs($admin)->post('/listings', $this->albumFields([
            'people' => [$friend->id],
            'latitude' => 46.67,
            'longitude' => 11.16,
            'placeName' => 'Merano',
            'videoLinks' => ['https://youtu.be/dQw4w9WgXcQ'],
            // A vertical phone photo, bigger than the limit
            'cover' => UploadedFile::fake()->image('photo.jpg', 2000, 3000),
        ]))->assertRedirect();

        $listing = Listing::where('title', 'Sea trip')->firstOrFail();
        $this->assertEquals('beach, Youtube', $listing->tags);
        $this->assertEquals('manual', $listing->coordinates_source);
        $this->assertEquals(46.67, (float) $listing->latitude);
        $this->assertStringEndsWith('.webp', $listing->logo);

        // Resized to 1600 on the long side, the photo keeps its shape (no bars, no crop)
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($listing->logo));
        $this->assertEquals([1067, 1600], [$width, $height]);
    }

    public function test_any_admin_can_edit_and_saving_confirms_a_suggested_place(): void
    {
        $creator = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $listing = Listing::factory()->create(['user_id' => $creator->id, 'latitude' => 46.5, 'longitude' => 11.3, 'coordinates_source' => 'suggested']);

        $this->actingAs($otherAdmin)->put("/listings/{$listing->id}", $this->albumFields([
            'title' => 'Renamed',
            'people' => [$creator->id],
            'latitude' => 46.5,
            'longitude' => 11.3,
        ]))->assertRedirect("/listings/{$listing->id}");

        $listing->refresh();
        $this->assertEquals('Renamed', $listing->title);
        $this->assertEquals('manual', $listing->coordinates_source);
    }

    public function test_non_admins_cannot_create_edit_or_delete_albums(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/listings/create')->assertForbidden();
        $this->actingAs($user)->post('/listings', $this->albumFields(['people' => [$user->id]]))->assertForbidden();
        $this->actingAs($user)->put("/listings/{$listing->id}", $this->albumFields(['people' => [$user->id]]))->assertForbidden();
        $this->actingAs($user)->delete("/listings/{$listing->id}")->assertForbidden();
        $this->assertModelExists($listing);
    }

    public function test_album_form_has_users_and_known_tags(): void
    {
        Listing::factory()->create(['tags' => 'mountains, friends']);
        Listing::factory()->create(['tags' => 'mountains, Youtube']);
        User::factory()->create(['is_demo' => true, 'name' => 'Guest']);

        $this->actingAs(User::factory()->admin()->create())->get('/listings/create')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Form')
                ->where('album', null)
                ->where('knownTags.0', 'mountains')
                ->where('users', fn ($users) => ! collect($users)->contains('name', 'Guest')));
    }

    public function test_map_shows_only_my_albums_with_a_place(): void
    {
        $user = User::factory()->create();
        Listing::factory()->create(['title' => 'Mine on map', 'latitude' => 46.5, 'longitude' => 11.3])->users()->attach($user);
        Listing::factory()->create(['title' => 'Mine no place'])->users()->attach($user);
        Listing::factory()->create(['title' => 'Not mine', 'latitude' => 45.0, 'longitude' => 9.0]);

        $this->actingAs($user)->get('/map')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Map')
                ->has('albums', 1)
                ->where('albums.0.title', 'Mine on map')
                ->where('withoutCoordinates', 1));
    }

    public function test_admin_reviews_suggested_places(): void
    {
        $admin = User::factory()->admin()->create();
        $good = Listing::factory()->create(['latitude' => 46.5, 'longitude' => 11.3, 'coordinates_source' => 'suggested']);
        $wrong = Listing::factory()->create(['latitude' => 40.0, 'longitude' => -3.0, 'coordinates_source' => 'suggested', 'place_name' => 'Spain']);

        $this->actingAs($admin)->get('/admin/locations')
            ->assertInertia(fn ($page) => $page->component('Admin/Locations')->has('albums', 2));

        $this->actingAs($admin)->post("/admin/locations/{$good->id}/confirm")->assertRedirect();
        $this->actingAs($admin)->delete("/admin/locations/{$wrong->id}")->assertRedirect();

        $this->assertEquals('manual', $good->fresh()->coordinates_source);
        $this->assertNull($wrong->fresh()->latitude);
        $this->assertNull($wrong->fresh()->place_name);

        $this->actingAs(User::factory()->create())->get('/admin/locations')->assertForbidden();
    }

    public function test_admin_confirms_all_remaining_suggestions_at_once(): void
    {
        Listing::factory()->count(3)->create(['latitude' => 46.5, 'longitude' => 11.3, 'coordinates_source' => 'suggested']);

        $this->actingAs(User::factory()->create())->post('/admin/locations/confirm-all')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post('/admin/locations/confirm-all')
            ->assertSessionHas('message', '3 locations confirmed');

        $this->assertEquals(0, Listing::where('coordinates_source', 'suggested')->count());
    }

    public function test_suggested_coordinates_are_imported_only_where_safe(): void
    {
        $plain = Listing::factory()->create(['location' => 'Merano']);
        $changed = Listing::factory()->create(['location' => 'Now somewhere else']);
        $alreadySet = Listing::factory()->create(['location' => 'Funes', 'latitude' => 46.6, 'longitude' => 11.7, 'coordinates_source' => 'manual']);

        $file = 'storage/framework/testing/coordinates.json';
        File::ensureDirectoryExists(base_path('storage/framework/testing'));
        File::put(base_path($file), json_encode([
            $plain->id => ['location_hash' => sha1('Merano'), 'latitude' => 46.67, 'longitude' => 11.16, 'place_name' => 'Merano'],
            $changed->id => ['location_hash' => sha1('Old place'), 'latitude' => 1, 'longitude' => 1, 'place_name' => 'Old'],
            $alreadySet->id => ['location_hash' => sha1('Funes'), 'latitude' => 40.0, 'longitude' => -3.0, 'place_name' => 'Funes, Spain'],
        ]));

        $this->artisan('albums:import-coordinates', ['file' => $file])->assertSuccessful();
        File::delete(base_path($file));

        $this->assertEquals('suggested', $plain->fresh()->coordinates_source);
        $this->assertNull($changed->fresh()->latitude);
        $this->assertEquals(46.6, (float) $alreadySet->fresh()->latitude);
    }

    public function test_maintenance_offers_the_one_time_album_jobs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/maintenance')
            ->assertSee('/admin/maintenance/trim-album-covers')
            ->assertSee('/admin/maintenance/import-album-coordinates');

        $this->actingAs($admin)->post('/admin/maintenance/trim-album-covers')
            ->assertSessionHas('output', fn ($output) => str_contains($output, 'album previews'));
    }

    private function albumFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sea trip',
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-05',
            'location' => 'Spiaggia',
            'albumLink' => 'https://www.amazon.com/photos/shared/abc',
            'tags' => ['beach'],
            'description' => '<p>Holidays</p>',
        ], $overrides);
    }
}
