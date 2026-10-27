<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Events (planned like trips, never an album), RSVP, polls, weather, calendar, people pages
class EventsTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $anna;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create(['name' => 'Muto']);
        $this->anna = User::factory()->create(['name' => 'Anna']);
        $this->bob = User::factory()->create(['name' => 'Bob']);
    }

    private function event(array $fields = [])
    {
        return $this->actingAs($this->me)->post('/trips', $fields + [
            'kind' => 'event', 'title' => 'Pizza', 'destination' => 'Bolzano', 'dates' => 'fixed',
            'date_from' => now()->addDays(3)->toDateString(), 'start_time' => '20:30', 'members' => [$this->anna->id, $this->bob->id],
        ]);
    }

    public function test_an_event_with_answers_and_its_own_list(): void
    {
        $this->event()->assertSessionHasNoErrors();
        $event = Trip::sole();
        $this->assertTrue($event->isEvent());
        $this->assertSame('20:30:00', $event->start_time);
        $this->assertSame($event->date_from->toDateString(), $event->date_to->toDateString());
        // The organizer is in; the invitation says when
        $this->assertSame('yes', $event->members->firstWhere('id', $this->me->id)->pivot->rsvp);
        $this->assertStringContainsString('Muto invited you to Pizza', app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getSubject());

        $this->actingAs($this->anna)->post("/trips/{$event->id}/rsvp", ['answer' => 'maybe'])->assertRedirect();
        $this->actingAs($this->bob)->post("/trips/{$event->id}/rsvp", ['answer' => 'no']);
        $this->actingAs($this->anna)->post("/trips/{$event->id}/rsvp", ['answer' => 'lol'])->assertSessionHasErrors('answer');

        // Events in their own list, trips in theirs
        Trip::create(['title' => 'Sicilia', 'created_by' => $this->me->id, 'date_from' => now()->addMonth(), 'date_to' => now()->addMonth()->addDays(5)])->members()->attach($this->me->id);
        $this->actingAs($this->me)->get('/trips')->assertInertia(fn ($page) => $page
            ->where('trips.0.title', 'Sicilia')
            ->has('trips', 1)
            ->where('events.0.title', 'Pizza')
            ->where('events.0.time', '20:30')
            ->where('events.0.goingCount', 1)
            ->where('events.0.myRsvp', 'yes'));
        $this->actingAs($this->anna)->get("/trips/{$event->id}")->assertInertia(fn ($page) => $page
            ->where('trip.kind', 'event')
            ->where('can.makeAlbum', false)
            ->where('members', fn ($members) => collect($members)->firstWhere('name', 'Bob')['rsvp'] === 'no'));

        // A stranger sees nothing; RSVP is for events only
        $this->actingAs(User::factory()->create())->get("/trips/{$event->id}")->assertForbidden();
        $trip = Trip::firstWhere('title', 'Sicilia');
        $this->actingAs($this->me)->post("/trips/{$trip->id}/rsvp", ['answer' => 'yes'])->assertForbidden();
    }

    public function test_the_day_before_not_to_who_said_no(): void
    {
        $this->event(['date_from' => now()->addDay()->toDateString()]);
        $event = Trip::sole();
        $this->actingAs($this->bob)->post("/trips/{$event->id}/rsvp", ['answer' => 'no']);
        app('mailer')->getSymfonyTransport()->flush();
        $this->fakeHttp['geocoding-api.open-meteo.com'] = ['results' => [['name' => 'Bolzano', 'country' => 'Italia', 'latitude' => 46.5, 'longitude' => 11.35]]];
        $this->fakeHttp['api.open-meteo.com/v1/forecast'] = ['daily' => ['time' => [now()->addDay()->toDateString()], 'weather_code' => [0], 'temperature_2m_max' => [24.4], 'temperature_2m_min' => [15.2], 'precipitation_probability_max' => [10]]];

        $this->artisan('trips:remind')->assertSuccessful();
        $sent = app('mailer')->getSymfonyTransport()->messages();
        $to = $sent->flatMap(fn ($message) => $message->getEnvelope()->getRecipients())->map->getAddress()->all();
        $this->assertEqualsCanonicalizing([$this->me->email, $this->anna->email], $to);
        $this->assertStringContainsString('Pizza: tomorrow!', $sent->first()->getOriginalMessage()->getSubject());
        $this->assertStringContainsString('20:30', $sent->first()->getOriginalMessage()->getHtmlBody());
        $this->assertStringContainsString('☀️ 24° / 15°', $sent->first()->getOriginalMessage()->getHtmlBody());
    }

    public function test_polls_and_weather_on_the_page(): void
    {
        $this->event(['date_from' => now()->addDays(2)->toDateString()]);
        $event = Trip::sole();
        $this->actingAs($this->anna)->post("/trips/{$event->id}/polls", ['question' => 'Pizza or sushi?', 'options' => ['Pizza', 'Sushi', '']])->assertSessionHasNoErrors();
        $this->actingAs($this->anna)->post("/trips/{$event->id}/polls", ['question' => 'Only one?', 'options' => ['Yes']])->assertSessionHasErrors('options');
        $poll = $event->polls()->sole();
        $sushi = $poll->options()->where('text', 'Sushi')->sole();
        $this->actingAs($this->bob)->post("/trips/{$event->id}/polls/{$poll->id}/options/{$sushi->id}/vote")->assertRedirect();

        $this->fakeHttp['geocoding-api.open-meteo.com'] = ['results' => [['name' => 'Bolzano', 'country' => 'Italia', 'latitude' => 46.5, 'longitude' => 11.35]]];
        $this->fakeHttp['api.open-meteo.com/v1/forecast'] = ['daily' => ['time' => [now()->addDays(2)->toDateString()], 'weather_code' => [61], 'temperature_2m_max' => [12], 'temperature_2m_min' => [5], 'precipitation_probability_max' => [80]]];
        $this->actingAs($this->me)->get("/trips/{$event->id}")->assertInertia(fn ($page) => $page
            ->where('polls.0.question', 'Pizza or sushi?')
            ->has('polls.0.options', 2)
            ->where('polls.0.options.1.voters', [$this->bob->id])
            ->where('polls.0.canDelete', true)
            ->where('weather.place', 'Bolzano, Italia')
            ->where('weather.days.0.rain', 80));

        // Only who made it (or the organizer) deletes it
        $this->actingAs($this->bob)->delete("/trips/{$event->id}/polls/{$poll->id}")->assertForbidden();
        $this->actingAs($this->anna)->delete("/trips/{$event->id}/polls/{$poll->id}")->assertRedirect();
        $this->assertSame(0, $event->polls()->count());
    }

    public function test_the_calendar_link(): void
    {
        $this->event(['date_from' => '2026-12-12']);
        $this->anna->update(['birthday' => '1996-08-03']);
        $this->actingAs($this->me)->post('/account/calendar')->assertRedirect();
        $token = $this->me->fresh()->calendar_token;
        $this->actingAs($this->me)->get('/account')->assertInertia(fn ($page) => $page->where('calendar', url("/calendar/{$token}.ics")));

        auth()->logout();
        $ics = $this->get("/calendar/{$token}.ics")->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:🎉 Pizza', $ics);
        // 20:30 in Italy in December is 19:30 UTC
        $this->assertStringContainsString('DTSTART:20261212T193000Z', $ics);
        $this->assertStringContainsString("SUMMARY:🎂 Anna's birthday", $ics);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $ics);

        // Said no: not in the calendar; a wrong link shows nothing
        $this->actingAs($this->me)->post('/trips/' . Trip::sole()->id . '/rsvp', ['answer' => 'no']);
        $this->assertStringNotContainsString('Pizza', $this->get("/calendar/{$token}.ics")->getContent());
        $this->get('/calendar/' . str_repeat('x', 48) . '.ics')->assertNotFound();
        $this->actingAs($this->me)->delete('/account/calendar');
        $this->get("/calendar/{$token}.ics")->assertNotFound();
    }

    public function test_people_pages_with_what_you_share_and_badges(): void
    {
        $together = Listing::factory()->create(['title' => 'Insieme', 'dateFrom' => '2016-05-01']);
        $together->users()->attach([$this->me->id, $this->anna->id]);
        $notMine = Listing::factory()->create(['title' => 'Segreto']);
        $notMine->users()->attach([$this->anna->id, $this->bob->id]);

        $this->actingAs($this->me)->get('/people')->assertInertia(fn ($page) => $page
            ->where('people', fn ($people) => collect($people)->firstWhere('name', 'Anna')['together'] === 1));
        $this->actingAs($this->me)->get("/people/{$this->anna->id}")->assertInertia(fn ($page) => $page
            ->where('stats.albums', 2)
            ->where('together.albums', 1)
            ->where('together.since', 2016)
            ->where('together.list', fn ($albums) => collect($albums)->pluck('title')->all() === ['Insieme'])
            ->where('badges', fn ($badges) => collect($badges)->pluck('key')->contains('explorer') && ! collect($badges)->pluck('key')->contains('legday')));
        // My own page: no "you and me"
        $this->actingAs($this->me)->get("/people/{$this->me->id}")->assertInertia(fn ($page) => $page->where('together', null));

        $demo = User::factory()->create(['is_demo' => true]);
        $this->actingAs($this->me)->get("/people/{$demo->id}")->assertNotFound();
        $this->actingAs($demo)->get('/people')->assertForbidden();
    }
}
