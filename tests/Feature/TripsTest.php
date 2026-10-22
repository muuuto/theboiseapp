<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Trip;
use App\Models\TripItem;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Upcoming trips: invitations, date poll, checklist, expenses, and becoming an album.
// Reads what the "array" mailer really sent
class TripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_a_trip_with_a_date_poll_invites_the_others(): void
    {
        $me = User::factory()->create(['name' => 'Muto']);
        $anna = User::factory()->create();

        $this->actingAs($me)->post('/trips', [
            'title' => 'Sicilia 2027',
            'destination' => 'Palermo',
            'members' => [$anna->id],
            'dates' => 'poll',
            'options' => [['from' => '2027-08-01', 'to' => '2027-08-08'], ['from' => '2027-08-15', 'to' => '2027-08-22']],
        ])->assertSessionHasNoErrors();

        $trip = Trip::firstWhere('title', 'Sicilia 2027');
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $trip->members->pluck('id')->all());
        $this->assertCount(2, $trip->options);
        $this->assertSame('planning', $trip->status());
        $this->assertSame([$anna->email], $this->emailRecipients());
        $this->assertStringContainsString('Muto invited you to Sicilia 2027', $this->sentMessages()->first()->getSubject());
    }

    public function test_a_poll_needs_two_options_and_never_the_demo_account(): void
    {
        $me = User::factory()->create();
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($me)->post('/trips', ['title' => 'X', 'dates' => 'poll', 'options' => [['from' => '2027-01-01', 'to' => '2027-01-02']]])->assertSessionHasErrors('options');
        $this->actingAs($me)->post('/trips', ['title' => 'X', 'dates' => 'fixed', 'date_from' => '2027-01-01', 'date_to' => '2027-01-02', 'members' => [$demo->id]])->assertSessionHasErrors('members');
        $this->actingAs($demo)->get('/trips')->assertForbidden();
        $this->assertSame(0, Trip::count());
    }

    public function test_only_the_people_going_see_a_trip(): void
    {
        [$trip, $me] = $this->trip();

        $this->actingAs($me)->get("/trips/{$trip->id}")->assertOk()->assertInertia(fn ($page) => $page->component('Trips/Show')->where('can.manage', true));
        $this->actingAs(User::factory()->create())->get("/trips/{$trip->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/trips/{$trip->id}")->assertForbidden();
    }

    public function test_voting_and_choosing_the_dates(): void
    {
        [$trip, $me, $anna] = $this->trip(poll: true);
        [$first, $second] = $trip->options;

        $this->actingAs($anna)->post("/trips/{$trip->id}/options/{$second->id}/vote");
        $this->actingAs($me)->post("/trips/{$trip->id}/options/{$second->id}/vote");
        $this->actingAs($anna)->post("/trips/{$trip->id}/options/{$first->id}/vote");
        $this->actingAs($anna)->post("/trips/{$trip->id}/options/{$first->id}/vote"); // changed her mind
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $second->voters()->pluck('users.id')->all());
        $this->assertSame(0, $first->voters()->count());

        // Only the organiser chooses; everyone else is told
        $this->actingAs($anna)->post("/trips/{$trip->id}/options/{$second->id}/choose")->assertForbidden();
        $this->actingAs($me)->post("/trips/{$trip->id}/options/{$second->id}/choose")->assertSessionHas('message');

        $this->assertSame('2027-08-15', $trip->fresh()->date_from->toDateString());
        $this->assertSame('upcoming', $trip->fresh()->status());
        $this->assertSame([$anna->email], $this->emailRecipients());
        $this->assertStringContainsString('dates decided', $this->sentMessages()->last()->getSubject());
    }

    public function test_any_member_suggests_dates_and_uses_the_list(): void
    {
        [$trip, $me, $anna] = $this->trip(poll: true);

        $this->actingAs($anna)->post("/trips/{$trip->id}/options", ['from' => '2027-09-01', 'to' => '2027-09-05'])->assertRedirect();
        $option = $trip->options()->reorder('id', 'desc')->first();
        $this->assertSame([$anna->id], $option->voters()->pluck('users.id')->all());

        $this->actingAs($anna)->post("/trips/{$trip->id}/items", ['text' => 'Tent', 'assigned_to' => $me->id]);
        $this->actingAs($anna)->post("/trips/{$trip->id}/items", ['text' => 'Speaker', 'assigned_to' => User::factory()->create()->id]);
        [$tent, $speaker] = TripItem::orderBy('id')->get();
        $this->assertSame($me->id, $tent->assigned_to);
        $this->assertNull($speaker->assigned_to); // not going: nobody

        $this->actingAs($me)->put("/trips/{$trip->id}/items/{$tent->id}", ['done' => true]);
        $this->assertTrue($tent->fresh()->done);

        // Anna's item: she or the organiser can remove it; a stranger can't touch the list
        $this->actingAs(User::factory()->create())->delete("/trips/{$trip->id}/items/{$tent->id}")->assertForbidden();
        $this->actingAs($me)->delete("/trips/{$trip->id}/items/{$speaker->id}")->assertRedirect();
        $this->assertModelMissing($speaker);
    }

    public function test_leaving_a_trip_removes_votes_and_jobs(): void
    {
        [$trip, $me, $anna] = $this->trip(poll: true);
        $option = $trip->options->first();
        $option->voters()->attach($anna->id);
        $item = $trip->items()->create(['text' => 'Tent', 'assigned_to' => $anna->id, 'created_by' => $me->id]);

        $this->actingAs($me)->put("/trips/{$trip->id}", ['title' => 'Sicilia 2027', 'dates' => 'poll', 'members' => [$me->id]])->assertSessionHasNoErrors();

        $this->assertSame([$me->id], $trip->fresh()->members->pluck('id')->all());
        $this->assertSame(0, $option->voters()->count());
        $this->assertNull($item->fresh()->assigned_to);
    }

    public function test_shared_expenses_for_the_trip(): void
    {
        [$trip, $me, $anna] = $this->trip();

        $this->actingAs($anna)->post("/trips/{$trip->id}/expenses")->assertRedirect();
        $group = $trip->fresh()->expenseGroup;
        $this->assertSame('Sicilia 2027', $group->name);
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $group->members->pluck('id')->all());

        // A second time: the same group
        $this->actingAs($me)->post("/trips/{$trip->id}/expenses")->assertRedirect("/expenses/{$group->id}");
    }

    public function test_after_the_trip_it_becomes_the_album(): void
    {
        [$trip, $me, $anna] = $this->trip();
        $trip->update(['date_from' => now()->subDays(10), 'date_to' => now()->subDays(3)]);
        $this->actingAs($me)->post("/trips/{$trip->id}/expenses");
        $this->assertSame('finished', $trip->fresh()->status());

        // Not allowed to post albums: no button
        $this->actingAs($anna)->get("/trips/{$trip->id}")->assertInertia(fn ($page) => $page->where('can.makeAlbum', false));
        $this->actingAs($anna)->get("/trips/{$trip->id}/album")->assertForbidden();

        $me->update(['isAdmin' => 1]);
        $this->actingAs($me)->get("/trips/{$trip->id}/album")->assertRedirect("/listings/create?trip={$trip->id}");
        $this->actingAs($me)->get("/listings/create?trip={$trip->id}")
            ->assertInertia(fn ($page) => $page->where('fromTrip.title', 'Sicilia 2027')->where('fromTrip.location', 'Palermo'));

        $this->actingAs($me)->post('/listings', [
            'title' => 'Sicilia 2027', 'dateFrom' => $trip->date_from->toDateString(), 'dateTo' => $trip->date_to->toDateString(),
            'location' => 'Palermo', 'albumLink' => 'https://www.amazon.it/photos/share/abc', 'tags' => ['mare'],
            'description' => '<p>x</p>', 'people' => [$me->id, $anna->id], 'trip_id' => $trip->id,
        ])->assertSessionHasNoErrors();

        $album = Listing::firstWhere('title', 'Sicilia 2027');
        $trip->refresh();
        $this->assertSame($album->id, $trip->listing_id);
        $this->assertSame('album', $trip->status());
        $this->assertSame($album->id, $trip->expenseGroup->listing_id);
        $this->actingAs($anna)->get("/listings/{$album->id}")->assertInertia(fn ($page) => $page->where('trip.id', $trip->id));
    }

    public function test_reminders_a_week_and_a_day_before(): void
    {
        [$soon] = $this->trip();
        $soon->update(['date_from' => now()->addDays(7)->toDateString(), 'date_to' => now()->addDays(10)->toDateString()]);
        $soon->items()->create(['text' => 'Tent']);
        [$later] = $this->trip();
        $later->update(['date_from' => now()->addDays(20)->toDateString()]);

        $this->artisan('trips:remind')->assertSuccessful();

        $this->assertCount(2, $this->sentMessages()); // the two people going on the soon one
        $this->assertStringContainsString('one week to go', $this->sentMessages()->first()->getSubject());
        $this->assertStringContainsString('1 thing still to sort out', $this->sentMessages()->first()->getHtmlBody());
    }

    // A trip organised by $me with Anna, fixed dates in 2027 (or a poll with two options)
    private function trip(bool $poll = false): array
    {
        [$me, $anna] = User::factory()->count(2)->create();
        $trip = Trip::create([
            'title' => 'Sicilia 2027', 'destination' => 'Palermo', 'created_by' => $me->id,
            'date_from' => $poll ? null : '2027-08-01', 'date_to' => $poll ? null : '2027-08-08',
        ]);
        $trip->members()->attach([$me->id, $anna->id]);
        if ($poll) {
            $trip->options()->create(['date_from' => '2027-08-01', 'date_to' => '2027-08-08']);
            $trip->options()->create(['date_from' => '2027-08-15', 'date_to' => '2027-08-22']);
        }

        return [$trip->fresh(['members', 'options']), $me, $anna];
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages()->map->getOriginalMessage();
    }

    private function emailRecipients(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->flatMap(fn ($message) => $message->getEnvelope()->getRecipients())
            ->map(fn ($address) => $address->getAddress())
            ->all();
    }
}
