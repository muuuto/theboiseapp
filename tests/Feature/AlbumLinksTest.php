<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Albums of the same trip posted by different people, linked from the album form
class AlbumLinksTest extends TestCase
{
    use RefreshDatabase;

    private function album(User $poster, array $people, array $fields = []): array
    {
        return $fields + [
            'title' => 'Sicilia', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-05', 'location' => 'Palermo',
            'albumLink' => 'https://photos.google.com/share/abc', 'description' => '<p>x</p>', 'cover' => $this->fakeCover(),
            'tags' => ['Anna', 'Bob', 'Carlo', 'Dario'], 'people' => collect($people)->pluck('id')->push($poster->id)->unique()->values()->all(),
        ];
    }

    public function test_linking_from_the_album_form(): void
    {
        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, true);
        $anna = User::factory()->create(['name' => 'Anna', 'can_post_albums' => true]);
        $bob = User::factory()->create(['name' => 'Bob', 'can_post_albums' => true]);
        $carlo = User::factory()->create(['can_post_albums' => true]);
        $bobs = Listing::factory()->create(['title' => 'Sicilia (Bob)', 'user_id' => $bob->id, 'dateFrom' => '2026-08-02']);
        $bobs->users()->attach([$anna->id, $bob->id, $carlo->id]);
        $hidden = Listing::factory()->create(['user_id' => $bob->id]);
        $hidden->users()->attach($bob->id);

        // The form offers the albums Anna is in (not the one she isn't)
        $this->actingAs($anna)->get('/listings/create')->assertInertia(fn ($page) => $page
            ->where('linkable', fn ($albums) => collect($albums)->pluck('id')->all() === [$bobs->id]));

        // Posting her own album of the same trip, linked to Bob's
        $this->actingAs($anna)->post('/listings', $this->album($anna, [$bob], ['title' => 'Sicilia (Anna)', 'sameTripAs' => $bobs->id]))->assertSessionHasNoErrors();
        $annas = Listing::firstWhere('title', 'Sicilia (Anna)');
        $this->assertNotNull($annas->trip_group);
        $this->assertSame($annas->trip_group, $bobs->fresh()->trip_group);

        // Each page shows the other; Carlo isn't in Anna's album, so he doesn't see it
        $this->actingAs($bob)->get("/listings/{$bobs->id}")->assertInertia(fn ($page) => $page->where('sameTrip.0.title', 'Sicilia (Anna)'));
        $this->actingAs($carlo)->get("/listings/{$bobs->id}")->assertInertia(fn ($page) => $page->where('sameTrip', []));
        // Editing: linked ones shown, not offered again
        $this->actingAs($anna)->get("/listings/{$annas->id}/edit")->assertInertia(fn ($page) => $page
            ->where('sameTrip.0.id', $bobs->id)
            ->where('linkable', []));

        // An album she isn't in can't be linked, even sent by hand
        $this->actingAs($anna)->put("/listings/{$annas->id}", $this->album($anna, [$bob], ['title' => 'Sicilia (Anna)', 'sameTripAs' => $hidden->id]));
        $this->assertNull($hidden->fresh()->trip_group);

        // Unlink from the form: a group of one isn't a group
        $this->actingAs($anna)->put("/listings/{$annas->id}", $this->album($anna, [$bob], ['title' => 'Sicilia (Anna)', 'unlinkTrip' => true]))->assertSessionHasNoErrors();
        $this->assertNull($annas->fresh()->trip_group);
        $this->assertNull($bobs->fresh()->trip_group);
    }
}
