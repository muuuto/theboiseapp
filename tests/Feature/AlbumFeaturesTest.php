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
        $listing = Listing::factory()->create(['videoLinks' => 'https://youtu.be/dQw4w9WgXcQ, https://vimeo.com/1']);
        $listing->places()->create(['latitude' => 46.67, 'longitude' => 11.16, 'place_name' => 'Merano']);
        $listing->users()->attach([$user->id, $friend->id]);
        $comment = Comment::factory()->create(['listing_id' => $listing->id, 'user_id' => $friend->id, 'comment' => '<p>Bello</p>']);
        $comment->likes()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/listings/{$listing->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Show')
                ->where('album.title', $listing->title)
                ->where('album.videoLinks', ['https://youtu.be/dQw4w9WgXcQ', 'https://vimeo.com/1'])
                ->where('album.places.0.latitude', 46.67)
                ->where('album.places.0.placeName', 'Merano')
                ->has('album.people', 2)
                ->where('comments.0.author', 'Zecca')
                ->where('comments.0.likes.count', 1)
                ->where('comments.0.likes.liked', true)
                ->where('comments.0.likes.names', ['Anna'])
                ->where('can.comment', true)
                ->where('can.edit', false)
                ->where('can.delete', false));
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

    public function test_admin_creates_an_album_with_cover_places_and_videos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $friend = User::factory()->create();

        $this->actingAs($admin)->post('/listings', $this->albumFields([
            'people' => [$friend->id],
            // An album in two places
            'places' => [
                ['latitude' => 51.5072, 'longitude' => -0.1276, 'placeName' => 'London'],
                ['latitude' => 53.3498, 'longitude' => -6.2603, 'placeName' => 'Dublin'],
            ],
            'videoLinks' => ['https://youtu.be/dQw4w9WgXcQ'],
            // A vertical phone photo, bigger than the limit
            'cover' => UploadedFile::fake()->image('photo.jpg', 2000, 3000),
        ]))->assertRedirect();

        $listing = Listing::where('title', 'Sea trip')->firstOrFail();
        $this->assertEquals('beach, Youtube', $listing->tags);
        $this->assertEquals(['London', 'Dublin'], $listing->places->pluck('place_name')->all());
        $this->assertEquals(['manual', 'manual'], $listing->places->pluck('source')->all());
        $this->assertStringEndsWith('.webp', $listing->logo);

        // Resized to 1600 on the long side, the photo keeps its shape (no bars, no crop)
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($listing->logo));
        $this->assertEquals([1067, 1600], [$width, $height]);
    }

    public function test_any_admin_can_edit_and_saving_confirms_a_suggested_place(): void
    {
        $creator = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $listing = Listing::factory()->create(['user_id' => $creator->id]);
        $listing->places()->create(['latitude' => 46.5, 'longitude' => 11.3, 'source' => 'suggested']);

        $this->actingAs($otherAdmin)->put("/listings/{$listing->id}", $this->albumFields([
            'title' => 'Renamed',
            'people' => [$creator->id],
            'places' => [['latitude' => 46.5, 'longitude' => 11.3]],
        ]))->assertRedirect("/listings/{$listing->id}");

        $listing->refresh();
        $this->assertEquals('Renamed', $listing->title);
        $this->assertEquals(['manual'], $listing->places->pluck('source')->all());
    }

    public function test_removing_every_place_in_the_form_clears_them(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = Listing::factory()->create();
        $listing->places()->create(['latitude' => 46.5, 'longitude' => 11.3]);

        $this->actingAs($admin)->put("/listings/{$listing->id}", $this->albumFields(['people' => [$admin->id]]));

        $this->assertCount(0, $listing->fresh()->places);
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
        foreach (['friends', 'mountains', 'Youtube'] as $name) {
            \App\Models\Tag::create(['name' => $name]);
        }
        User::factory()->create(['is_demo' => true, 'name' => 'Guest']);

        $this->actingAs(User::factory()->admin()->create())->get('/listings/create')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Form')
                ->where('album', null)
                // The tags library, A to Z, without the automatic Youtube
                ->where('tagLibrary', fn ($tags) => collect($tags)->pluck('name')->all() === ['friends', 'mountains'])
                // The demo account can be added to albums (that's what recruiters see)
                ->where('users', fn ($users) => collect($users)->firstWhere('name', 'Guest')['isDemo'] === true));
    }

    public function test_map_shows_a_pin_per_place_of_my_albums(): void
    {
        $user = User::factory()->create();
        $trip = Listing::factory()->create(['title' => 'Londra e Dublino']);
        $trip->users()->attach($user);
        $trip->places()->create(['latitude' => 51.5, 'longitude' => -0.12, 'position' => 0]);
        $trip->places()->create(['latitude' => 53.3, 'longitude' => -6.26, 'position' => 1]);
        Listing::factory()->create(['title' => 'Mine no place'])->users()->attach($user);
        Listing::factory()->create(['title' => 'Not mine'])->places()->create(['latitude' => 45.0, 'longitude' => 9.0]);

        $this->actingAs($user)->get('/map')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Map')
                ->has('places', 2)
                ->where('places.0.albumId', $trip->id)
                ->where('places.1.albumId', $trip->id)
                ->where('withoutPlaces', 1));
    }

    public function test_admin_reviews_suggested_places(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = Listing::factory()->create();
        $good = $listing->places()->create(['latitude' => 51.5, 'longitude' => -0.12, 'source' => 'suggested']);
        $wrong = $listing->places()->create(['latitude' => 40.0, 'longitude' => -3.0, 'source' => 'suggested', 'place_name' => 'Spain']);
        Listing::factory()->create(['title' => 'No place at all']);

        $this->actingAs($admin)->get('/admin/locations')
            ->assertInertia(fn ($page) => $page->component('Admin/Locations')->has('places', 2)->where('missing.0.title', 'No place at all'));

        $this->actingAs($admin)->post("/admin/locations/{$good->id}/confirm")->assertRedirect();
        $this->actingAs($admin)->delete("/admin/locations/{$wrong->id}")->assertRedirect();

        // One place confirmed, the wrong one gone, the album keeps the good one
        $this->assertEquals('manual', $good->fresh()->source);
        $this->assertModelMissing($wrong);
        $this->assertCount(1, $listing->fresh()->places);

        $this->actingAs(User::factory()->create())->get('/admin/locations')->assertForbidden();
    }

    public function test_admin_confirms_all_remaining_suggestions_at_once(): void
    {
        foreach (Listing::factory()->count(3)->create() as $listing) {
            $listing->places()->create(['latitude' => 46.5, 'longitude' => 11.3, 'source' => 'suggested']);
        }

        $this->actingAs(User::factory()->create())->post('/admin/locations/confirm-all')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post('/admin/locations/confirm-all')
            ->assertSessionHas('message', '3 locations confirmed');

        $this->assertEquals(0, \App\Models\ListingPlace::where('source', 'suggested')->count());
    }

    public function test_import_completes_reviewed_albums_without_undoing_the_review(): void
    {
        // Like production after the first import and some review:
        $london = Listing::factory()->create(['location' => 'Londra e Dublino']);
        $london->places()->create(['latitude' => 51.5072, 'longitude' => -0.1276, 'place_name' => 'Londra', 'source' => 'manual']);
        $removed = Listing::factory()->create(['location' => 'Montebello']); // wrong guess removed by the admin
        $moved = Listing::factory()->create(['location' => 'Funes & Passo delle Erbe']);
        $moved->places()->create(['latitude' => 46.0, 'longitude' => 12.0, 'source' => 'manual']); // fixed by hand elsewhere
        $changed = Listing::factory()->create(['location' => 'Now somewhere else']);
        $changed->places()->create(['latitude' => 51.5072, 'longitude' => -0.1276]);

        $file = $this->suggestions([
            $london->id => ['Londra e Dublino', [[51.5072, -0.1276, 'Londra'], [53.3498, -6.2603, 'Dublino']]],
            $removed->id => ['Montebello', [[34.0, -118.1, 'Montebello, California']]],
            $moved->id => ['Funes & Passo delle Erbe', [[46.64, 11.7, 'Funes'], [46.6, 11.8, 'Passo delle Erbe']]],
            $changed->id => ['Old place', [[51.5072, -0.1276, 'Londra'], [53.3498, -6.2603, 'Dublino']]],
        ]);

        $this->artisan('albums:import-coordinates', ['file' => $file])->assertSuccessful();
        $this->artisan('albums:import-coordinates', ['file' => $file])->assertSuccessful(); // twice: nothing more

        // London kept as confirmed, Dublin added as a suggestion
        $this->assertEquals(['Londra', 'Dublino'], $london->places()->pluck('place_name')->all());
        $this->assertEquals(['manual', 'suggested'], $london->places()->pluck('source')->all());
        // The removed guess doesn't come back, the hand-fixed place and the changed album are untouched
        $this->assertCount(0, $removed->places);
        $this->assertCount(1, $moved->places);
        $this->assertCount(1, $changed->places);
    }

    public function test_import_fills_empty_albums_only_when_asked(): void
    {
        $trip = Listing::factory()->create(['location' => 'Londra e Dublino']);
        $file = $this->suggestions([$trip->id => ['Londra e Dublino', [[51.5, -0.12, 'Londra'], [53.3, -6.26, 'Dublino']]]]);

        $this->artisan('albums:import-coordinates', ['file' => $file])->assertSuccessful();
        $this->assertCount(0, $trip->places);

        $this->artisan('albums:import-coordinates', ['file' => $file, '--include-empty' => true])->assertSuccessful();
        $this->assertEquals(['suggested', 'suggested'], $trip->places()->pluck('source')->all());
    }

    // Writes a suggestions file: [id => [location text, [[lat, lng, name], ...]]]
    private function suggestions(array $albums): string
    {
        $file = 'storage/framework/testing/coordinates.json';
        File::ensureDirectoryExists(base_path('storage/framework/testing'));
        File::put(base_path($file), json_encode(collect($albums)->map(fn ($album) => [
            'location_hash' => sha1($album[0]),
            'places' => array_map(fn ($place) => ['latitude' => $place[0], 'longitude' => $place[1], 'place_name' => $place[2]], $album[1]),
        ])));

        return $file;
    }

    public function test_maintenance_offers_the_one_time_album_jobs(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/maintenance')->assertInertia(fn ($page) => $page->component('Admin/Maintenance'));

        $this->actingAs($admin)->post('/admin/maintenance/trim-album-covers')
            ->assertSessionHas('output', fn ($output) => str_contains($output, 'album previews'));
    }

    public function test_every_album_needs_a_cover(): void
    {
        $admin = User::factory()->admin()->create();

        // New album: required
        $this->actingAs($admin)->post('/listings', $this->albumFields(['people' => [$admin->id], 'cover' => null]))->assertSessionHasErrors('cover');
        $this->assertSame(0, Listing::count());

        // Editing one that has a cover: no need to upload it again
        $album = Listing::factory()->create();
        $album->users()->attach($admin);
        $this->actingAs($admin)->put("/listings/{$album->id}", $this->albumFields(['people' => [$admin->id], 'cover' => null]))->assertSessionHasNoErrors();

        // Editing one without: required
        $bare = Listing::factory()->create(['logo' => null]);
        $this->actingAs($admin)->put("/listings/{$bare->id}", $this->albumFields(['people' => [$admin->id], 'cover' => null]))->assertSessionHasErrors('cover');
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
            'cover' => $this->fakeCover(),
        ], $overrides);
    }
}
