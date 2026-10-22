<?php

namespace Tests\Feature;

use App\Models\AlbumEvent;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Members posting albums: switched on by the admins (site switch + per person), traced in the history.
// Reads what the "array" mailer really sent (Mail::send with a view isn't caught by Mail::fake)
class AlbumPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nobody_but_admins_posts_while_the_switch_is_off(): void
    {
        $zecca = User::factory()->create(['can_post_albums' => true]);

        $this->actingAs($zecca)->get('/listings/create')->assertForbidden();
        $this->actingAs($zecca)->post('/listings', $this->album([$zecca->id]))->assertForbidden();
        $this->actingAs($zecca)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.canPostAlbums', false));
    }

    public function test_switch_on_but_not_allowed_still_cant_post(): void
    {
        $this->postingOn();
        $anna = User::factory()->create();

        $this->actingAs($anna)->post('/listings', $this->album([$anna->id]))->assertForbidden();
    }

    public function test_an_allowed_member_posts_an_album(): void
    {
        $this->postingOn();
        $admin = User::factory()->admin()->create();
        $zecca = User::factory()->create(['name' => 'Zecca', 'can_post_albums' => true]);
        $anna = User::factory()->create(['name' => 'Anna']);
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($zecca)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.canPostAlbums', true));
        $this->actingAs($zecca)->post('/listings', $this->album([$anna->id, $demo->id], [
            'personal' => [['userId' => $anna->id, 'albumLink' => 'https://example.com/secret', 'description' => '']],
        ]))->assertRedirect();

        $album = Listing::firstWhere('title', 'Sicilia 2026');
        $this->assertEquals($zecca->id, $album->user_id);
        // Zecca is always in it, the demo account only by an admin, no personal versions
        $this->assertEqualsCanonicalizing([$zecca->id, $anna->id], $album->users->pluck('id')->all());
        $this->assertNull(ListingUser::where('listing_id', $album->id)->where('user_id', $anna->id)->value('custom_album_link'));

        // Anna gets "Zecca posted a new album", the admin (not in it) a heads-up, Zecca nothing
        $sent = $this->sentMessages()->mapWithKeys(fn ($message) => [$message->getEnvelope()->getRecipients()[0]->getAddress() => $message->getOriginalMessage()]);
        $this->assertEqualsCanonicalizing([$anna->email, $admin->email], $sent->keys()->all());
        $this->assertStringContainsString('Zecca posted a new album', $sent[$anna->email]->getHtmlBody());
        $this->assertStringContainsString('Zecca posted the album', $sent[$admin->email]->getSubject());

        $event = AlbumEvent::firstWhere('action', 'created');
        $this->assertEquals($zecca->id, $event->user_id);
        $this->assertEquals(['Anna', 'Zecca'], $event->details['people']);
    }

    public function test_posters_form_has_no_personal_versions_and_no_demo_account(): void
    {
        $this->postingOn();
        $zecca = User::factory()->create(['can_post_albums' => true]);
        $anna = User::factory()->create();
        User::factory()->create(['is_demo' => true]);
        $album = Listing::factory()->create(['user_id' => $zecca->id]);
        $album->users()->attach([$zecca->id, $anna->id]);
        // An admin gave Anna her own link: Zecca must not find it in the page data
        ListingUser::where('listing_id', $album->id)->where('user_id', $anna->id)->update(['custom_album_link' => 'https://example.com/anna']);

        $this->actingAs($zecca)->get("/listings/{$album->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('canPersonalize', false)
                ->where('album.personal', [])
                ->where('alwaysIn', $zecca->id)
                ->where('users', fn ($users) => collect($users)->where('isDemo', true)->isEmpty()));
    }

    public function test_poster_edits_own_albums_only_and_history_records_what_changed(): void
    {
        $this->postingOn();
        $zecca = User::factory()->create(['name' => 'Zecca', 'can_post_albums' => true]);
        $anna = User::factory()->create(['name' => 'Anna']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $demo = User::factory()->create(['is_demo' => true]);
        $mine = Listing::factory()->create(['user_id' => $zecca->id, 'title' => 'Old title']);
        $mine->users()->attach([$zecca->id, $anna->id, $demo->id]);
        ListingUser::where('listing_id', $mine->id)->where('user_id', $anna->id)->update(['custom_description' => '<p>Only for Anna</p>']);
        $notMine = Listing::factory()->create();
        $notMine->users()->attach($zecca->id);

        $this->actingAs($zecca)->put("/listings/{$notMine->id}", $this->album([$zecca->id]))->assertForbidden();
        $this->actingAs($zecca)->put("/listings/{$mine->id}", $this->album([$bob->id], ['title' => 'New title']))->assertRedirect();

        // Zecca stays (always in), the demo account an admin added stays, Anna's personal version stays
        $this->assertEqualsCanonicalizing([$zecca->id, $bob->id, $demo->id], $mine->fresh()->users->pluck('id')->all());
        $this->assertEquals('New title', $mine->fresh()->title);

        $event = AlbumEvent::firstWhere('action', 'updated');
        $this->assertEquals(['Bob'], $event->details['added']);
        $this->assertEquals(['Anna'], $event->details['removed']);
        // assertContainsEquals: MySQL stores the JSON keys in its own order
        $this->assertContainsEquals(['field' => 'title', 'from' => 'Old title', 'to' => 'New title'], $event->details['changes']);
    }

    public function test_poster_keeps_personal_versions_when_editing(): void
    {
        $this->postingOn();
        $zecca = User::factory()->create(['can_post_albums' => true]);
        $anna = User::factory()->create();
        $album = Listing::factory()->create(['user_id' => $zecca->id]);
        $album->users()->attach([$zecca->id, $anna->id]);
        ListingUser::where('listing_id', $album->id)->where('user_id', $anna->id)->update(['custom_description' => '<p>Only for Anna</p>']);

        $this->actingAs($zecca)->put("/listings/{$album->id}", $this->album([$zecca->id, $anna->id]));

        $this->assertEquals('<p>Only for Anna</p>', ListingUser::where('listing_id', $album->id)->where('user_id', $anna->id)->value('custom_description'));
    }

    public function test_only_admins_delete_and_the_history_keeps_the_title(): void
    {
        $this->postingOn();
        $zecca = User::factory()->create(['can_post_albums' => true]);
        $album = Listing::factory()->create(['user_id' => $zecca->id, 'title' => 'Gone']);
        $album->users()->attach($zecca->id);

        $this->actingAs($zecca)->delete("/listings/{$album->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete("/listings/{$album->id}")->assertRedirect('/');

        $this->assertModelMissing($album);
        $this->assertEquals('Gone', AlbumEvent::firstWhere('action', 'deleted')->listing_title);
    }

    public function test_without_the_permission_posters_cant_edit_their_albums_anymore(): void
    {
        $this->postingOn();
        $zecca = User::factory()->create(['can_post_albums' => true]);
        $album = Listing::factory()->create(['user_id' => $zecca->id]);
        $album->users()->attach($zecca->id);
        $this->actingAs($zecca)->get("/listings/{$album->id}/edit")->assertOk();

        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, false);
        $this->actingAs($zecca)->get("/listings/{$album->id}/edit")->assertForbidden();

        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, true);
        $zecca->update(['can_post_albums' => false]);
        $this->actingAs($zecca)->get("/listings/{$album->id}/edit")->assertForbidden();
        // The album stays
        $this->assertModelExists($album);
    }

    public function test_only_albums_i_posted_on_the_list_and_the_map(): void
    {
        $me = User::factory()->admin()->create();
        $zecca = User::factory()->create();
        $mine = Listing::factory()->create(['user_id' => $me->id]);
        $theirs = Listing::factory()->create(['user_id' => $zecca->id]);
        foreach ([$mine, $theirs] as $album) {
            $album->users()->attach($me->id);
            $album->places()->create(['latitude' => 46, 'longitude' => 11, 'place_name' => 'Bolzano']);
        }

        $this->actingAs($me)->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.available', true)->where('ownFilter.on', false)->where('albums.total', 2));

        $this->actingAs($me)->put('/account/album-filter', ['on' => true]);

        $this->actingAs($me)->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.on', true)->where('albums.total', 1)->where('albums.data.0.id', $mine->id));
        $this->actingAs($me)->get('/map')->assertInertia(fn ($page) => $page->has('places', 1)->where('places.0.albumId', $mine->id));
        // Someone who never posted doesn't get the toggle
        $this->actingAs($zecca)->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.available', true));
        $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.available', false));
    }

    public function test_my_albums_choice_on_the_account_page(): void
    {
        $user = User::factory()->create();
        Listing::factory()->create(['user_id' => $user->id])->users()->attach($user);

        // Without a choice: all albums
        $this->actingAs($user)->get('/account')->assertInertia(fn ($page) => $page->where('ownFilter.available', true)->where('ownFilter.on', false));
        $this->actingAs($user)->put('/account/album-filter', ['on' => true]);
        $this->actingAs($user)->get('/account')->assertInertia(fn ($page) => $page->where('ownFilter.on', true));
        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.on', true));
        // The switch on the album list: only this view, the default stays
        $this->actingAs($user)->get('/?mine=0')->assertInertia(fn ($page) => $page->where('ownFilter.on', false)->where('ownFilter.default', true));
        $this->assertTrue((bool) $user->fresh()->only_own_albums);
        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page->where('ownFilter.on', true));
        $this->actingAs($user)->get('/map?mine=0')->assertInertia(fn ($page) => $page->where('ownFilter.on', false));
        // Who never posted an album doesn't get it
        $this->actingAs(User::factory()->create())->get('/account')->assertInertia(fn ($page) => $page->where('ownFilter.available', false));
    }

    public function test_admins_open_any_album_read_only_and_see_its_history(): void
    {
        $admin = User::factory()->admin()->create();
        $zecca = User::factory()->create();
        $album = Listing::factory()->create(['user_id' => $zecca->id]);
        $album->users()->attach($zecca->id);
        AlbumEvent::record('created', $album, ['people' => [$zecca->name]], $zecca);

        $this->actingAs($admin)->get("/listings/{$album->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.adminView', true)->where('can.comment', false)->has('history', 1));
        $this->actingAs($zecca)->get("/listings/{$album->id}")
            ->assertInertia(fn ($page) => $page->where('can.adminView', false)->where('history', []));
        $this->actingAs(User::factory()->create())->get("/listings/{$album->id}")->assertForbidden();
        $this->actingAs($admin)->post('/listings/comments', ['listing_id' => $album->id, 'comment' => 'hi'])->assertForbidden();
    }

    public function test_maintenance_switches_are_admin_only_and_recorded(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Muto']);
        $zecca = User::factory()->create(['name' => 'Zecca']);
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($zecca)->post('/admin/maintenance/album-posting', ['enabled' => true])->assertForbidden();
        $this->actingAs($admin)->post('/admin/maintenance/album-posting', ['enabled' => true])->assertSessionHas('message');
        $this->actingAs($admin)->put("/admin/maintenance/album-posting/{$zecca->id}", ['canPost' => true]);
        $this->actingAs($admin)->put("/admin/maintenance/album-posting/{$demo->id}", ['canPost' => true])->assertStatus(422);

        $this->assertTrue((bool) $zecca->fresh()->can_post_albums);
        $this->assertTrue($zecca->fresh()->canPostAlbums());
        $this->assertFalse($demo->fresh()->canPostAlbums());
        $this->actingAs($admin)->get('/admin/maintenance')
            ->assertInertia(fn ($page) => $page
                ->where('albumPosting.enabled', true)
                ->where('albumPosting.history.0.summary', 'Muto allowed Zecca to post albums')
                ->where('albumPosting.history.1.summary', 'Muto switched on album posting for members'));
    }

    public function test_allow_everyone_and_no_one(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Muto']);
        [$anna, $zecca] = User::factory()->count(2)->create();
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($anna)->put('/admin/maintenance/album-posting/everyone', ['canPost' => true])->assertForbidden();
        $this->actingAs($admin)->put('/admin/maintenance/album-posting/everyone', ['canPost' => true])->assertSessionHas('message', 'Everyone can post albums');

        $this->assertTrue((bool) $anna->fresh()->can_post_albums);
        $this->assertTrue((bool) $zecca->fresh()->can_post_albums);
        $this->assertFalse((bool) $demo->fresh()->can_post_albums);
        $this->assertSame('Muto allowed everyone to post albums', \App\Support\AlbumHistory::describe(AlbumEvent::latest('id')->first())['summary']);

        $this->actingAs($admin)->put('/admin/maintenance/album-posting/everyone', ['canPost' => false]);
        $this->assertFalse((bool) $anna->fresh()->can_post_albums);
        $this->assertFalse((bool) $zecca->fresh()->can_post_albums);
    }

    private function postingOn(): void
    {
        Settings::set(Settings::MEMBERS_CAN_POST_ALBUMS, true);
    }

    private function album(array $people, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sicilia 2026',
            'dateFrom' => '2026-08-01',
            'dateTo' => '2026-08-08',
            'location' => 'Palermo',
            'albumLink' => 'https://www.amazon.it/photos/share/abc',
            'tags' => ['beach'],
            'description' => '<p>Holidays</p>',
            'people' => $people,
            'cover' => $this->fakeCover(),
        ], $overrides);
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
