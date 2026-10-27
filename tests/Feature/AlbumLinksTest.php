<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Albums of the same trip posted by different people, linked together
class AlbumLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_linking_albums_of_the_same_trip(): void
    {
        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, true);
        $anna = User::factory()->create(['name' => 'Anna', 'can_post_albums' => true]);
        $bob = User::factory()->create(['name' => 'Bob', 'can_post_albums' => true]);
        $carlo = User::factory()->create(['can_post_albums' => true]);
        $mine = Listing::factory()->create(['title' => 'Sicilia (Anna)', 'user_id' => $anna->id, 'dateFrom' => '2026-08-01']);
        $theirs = Listing::factory()->create(['title' => 'Sicilia (Bob)', 'user_id' => $bob->id, 'dateFrom' => '2026-08-02']);
        $far = Listing::factory()->create(['title' => 'Natale', 'user_id' => $bob->id, 'dateFrom' => '2025-12-24']);
        $mine->users()->attach([$anna->id, $bob->id]);
        $theirs->users()->attach([$anna->id, $bob->id, $carlo->id]);
        $far->users()->attach([$anna->id, $bob->id]);

        // Anna can link her album: to the ones she's in, closest dates first
        $this->actingAs($anna)->get("/listings/{$mine->id}")->assertInertia(fn ($page) => $page
            ->where('sameTrip', [])
            ->where('linkable.0.title', 'Sicilia (Bob)')
            ->where('linkable.1.title', 'Natale'));
        $this->actingAs($anna)->post("/listings/{$mine->id}/links", ['with' => $theirs->id])->assertSessionHasNoErrors();
        $this->assertSame($mine->fresh()->trip_group, $theirs->fresh()->trip_group);

        // Bob sees Anna's album from his; Carlo isn't in Anna's album, so he doesn't
        $this->actingAs($bob)->get("/listings/{$theirs->id}")->assertInertia(fn ($page) => $page->where('sameTrip.0.title', 'Sicilia (Anna)')->where('sameTrip.0.postedBy', 'Anna'));
        $this->actingAs($carlo)->get("/listings/{$theirs->id}")->assertInertia(fn ($page) => $page->where('sameTrip', [])->where('linkable', null));

        // A third album joins the group
        $third = Listing::factory()->create(['user_id' => $carlo->id]);
        $third->users()->attach([$carlo->id, $anna->id]);
        $this->actingAs($carlo)->post("/listings/{$third->id}/links", ['with' => $theirs->id]);
        $this->assertSame(3, Listing::where('trip_group', $mine->fresh()->trip_group)->count());

        // Only who can edit an album links it, and only to albums they're in
        $this->actingAs($carlo)->post("/listings/{$mine->id}/links", ['with' => $third->id])->assertForbidden();
        $this->actingAs($carlo)->post("/listings/{$third->id}/links", ['with' => $far->id])->assertForbidden();

        // Out of the group; the last two stay linked
        $this->actingAs($anna)->delete("/listings/{$mine->id}/links")->assertRedirect();
        $this->assertNull($mine->fresh()->trip_group);
        $this->assertNotNull($theirs->fresh()->trip_group);
        $this->actingAs($carlo)->delete("/listings/{$third->id}/links");
        // A group of one isn't a group
        $this->assertNull($theirs->fresh()->trip_group);
    }
}
