<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Listing;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushSender;
use App\Support\Wrapped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

// TheBoise Wrapped: the numbers, when it opens, and the December notification
class WrappedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_year_in_numbers(): void
    {
        $me = User::factory()->create();
        $anna = User::factory()->create(['name' => 'Anna']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $demo = User::factory()->create(['is_demo' => true]);
        // Palermo (2 nights) and Tenerife (1 night) with Anna; Bolzano itself with Bob
        $palermo = $this->album('Sicilia', '2025-08-01', '2025-08-03', [[38.1157, 13.3615, 'Palermo, Sicilia, Italia']], [$me, $anna, $demo]);
        $this->album('Bene Life', '2025-05-17', '2025-05-18', [[28.2916, -16.6291, 'Tenerife, Canarie, Spagna']], [$me, $anna]);
        $this->album('Spritz', '2025-05-30', '2025-05-30', [[46.4983, 11.3548, 'Bolzano, Alto Adige, Italia']], [$me, $bob]);
        // Another year doesn't count
        $this->album('Old', '2024-01-01', '2024-01-02', [], [$me]);
        $comment = Comment::create(['listing_id' => $palermo->id, 'user_id' => $me->id, 'comment' => '<p>Che <b>mare</b>!</p>', 'created_at' => '2025-08-04 10:00:00']);
        $comment->likes()->create(['user_id' => $anna->id]);
        $comment->likes()->create(['user_id' => $bob->id]);

        $w = Wrapped::for($me, 2025);

        $this->assertSame(3, $w['albumCount']);
        $this->assertSame(1, $w['previousYear']);
        $this->assertSame(3 + 2 + 1, $w['daysAway']);
        // In the order of the first visit: Tenerife (May) before Sicily
        $this->assertSame(['Spagna' => '🇪🇸', 'Italia' => '🇮🇹'], $w['countries']->pluck('flag', 'name')->all());
        $this->assertSame('Tenerife', $w['farthest']['name']);
        $this->assertEqualsWithDelta(3165, $w['farthest']['km'], 15);
        // There and back: about 2 × (Palermo 947 + Tenerife 3165), Bolzano itself 0
        $this->assertEqualsWithDelta(2 * (947 + 3165), $w['km'], 30);
        $this->assertSame(['name' => 'Sicilia', 'days' => 3], ['name' => $w['longest']['title'], 'days' => $w['longest']['days']]);
        $this->assertSame('May', $w['busiestMonth']['name']);
        // Anna first (2 albums), never the demo account
        $this->assertSame(['Anna' => 2, 'Bob' => 1], $w['friends']->mapWithKeys(fn ($f) => [$f['name'] => $f['trips']])->all());
        $this->assertSame(['text' => 'Che mare!', 'likes' => 2, 'on' => 'Sicilia'], $w['comments']['best']);

        $this->assertNull(Wrapped::for($me, 2023));
    }

    public function test_kilometres_from_each_persons_hometown(): void
    {
        $me = User::factory()->create();
        $this->album('Sicilia', '2025-08-01', '2025-08-03', [[38.1157, 13.3615, 'Palermo, Sicilia, Italia']], [$me]);
        $this->album('Spritz', '2025-05-30', '2025-05-30', [[46.4983, 11.3548, 'Bolzano, Alto Adige, Italia']], [$me]);

        // Without a hometown: from Bolzano
        $this->assertSame('Bolzano', Wrapped::for($me, 2025)['home']);
        $this->assertSame('Palermo', Wrapped::for($me, 2025)['farthest']['name']);

        // From Palermo: Palermo is home, Bolzano is the trip (about 947 km each way)
        $this->actingAs($me)->put('/account/home', ['name' => 'Palermo', 'latitude' => 38.1157, 'longitude' => 13.3615])->assertSessionHas('message', 'Hometown saved');
        $w = Wrapped::for($me->fresh(), 2025);
        $this->assertSame('Palermo', $w['home']);
        $this->assertSame('Bolzano', $w['farthest']['name']);
        $this->assertEqualsWithDelta(947, $w['farthest']['km'], 10);
        $this->assertEqualsWithDelta(2 * 947, $w['km'], 20);

        $this->actingAs($me)->get('/account')->assertInertia(fn ($page) => $page->where('home', ['name' => 'Palermo', 'custom' => true]));
        $this->actingAs($me)->put('/account/home', ['name' => 'Nowhere', 'latitude' => 120, 'longitude' => 0])->assertSessionHasErrors('latitude');
        $this->actingAs($me)->put('/account/home', ['reset' => true]);
        $this->assertSame('Bolzano', Wrapped::for($me->fresh(), 2025)['home']);
        $this->actingAs($me)->get('/account')->assertInertia(fn ($page) => $page->where('home', ['name' => 'Bolzano', 'custom' => false]));

        $demo = User::factory()->create(['is_demo' => true]);
        $this->actingAs($demo)->put('/account/home', ['name' => 'Roma', 'latitude' => 41.9, 'longitude' => 12.5])->assertForbidden();
        $this->assertNull($demo->fresh()->home_name);
    }

    public function test_the_highest_place_from_500_metres(): void
    {
        $me = User::factory()->create();
        $this->fakeHttp['api.open-meteo.com'] = fn ($request) => ['elevation' => array_map(fn ($lat) => (float) $lat > 46.4 ? 2244.6 : 14.0, explode(',', $request['latitude']))];

        // Saving an album looks up the altitude of its places
        $me->update(['isAdmin' => 1]);
        $this->actingAs($me)->post('/listings', [
            'title' => 'Seceda', 'dateFrom' => '2025-07-01', 'dateTo' => '2025-07-01', 'location' => 'Val Gardena',
            'albumLink' => 'https://www.amazon.it/photos/share/abc', 'tags' => ['montagna'], 'description' => '<p>x</p>', 'cover' => $this->fakeCover(),
            'people' => [$me->id], 'places' => [['latitude' => 46.6008, 'longitude' => 11.7244, 'placeName' => 'Seceda, Alto Adige, Italia']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2245, \App\Models\ListingPlace::sole()->elevation);

        $this->album('Mare', '2025-08-01', '2025-08-03', [[38.1157, 13.3615, 'Palermo, Sicilia, Italia']], [$me]);
        $w = Wrapped::for($me->fresh(), 2025);
        $this->assertSame(['name' => 'Seceda', 'album' => 'Seceda', 'meters' => 2245], $w['highest']);

        // The nightly job fills in the old ones; below 500 m there's no slide
        $this->artisan('albums:import-elevations')->expectsOutputToContain('1 of 1')->assertSuccessful();
        $this->assertSame(14, \App\Models\ListingPlace::where('place_name', 'like', 'Palermo%')->value('elevation'));
        \App\Models\Listing::where('title', 'Seceda')->delete();
        $this->assertNull(Wrapped::for($me->fresh(), 2025)['highest']);
    }

    public function test_badges_unlocked_during_the_year(): void
    {
        $me = User::factory()->create();
        // 8 countries before 2025, 2 more in 2025: Explorer (10 countries) is a 2025 badge
        $countries = ['Francia', 'Spagna', 'Austria', 'Germania', 'Grecia', 'Croazia', 'Slovenia', 'Svizzera', 'Portogallo', 'Irlanda'];
        foreach ($countries as $i => $country) {
            $year = $i < 8 ? 2024 : 2025;
            $this->album("Trip {$i}", "{$year}-0" . ($i % 9 + 1) . '-01', "{$year}-0" . ($i % 9 + 1) . '-02', [[45, 10, "Somewhere, {$country}"]], [$me]);
        }

        $this->assertSame([['key' => 'explorer', 'target' => 10]], Wrapped::for($me, 2025)['badges']->all());
        $this->assertSame([], Wrapped::for($me, 2024)['badges']->all());
    }

        public function test_the_current_year_opens_in_december(): void
    {
        $me = User::factory()->create();
        $this->album('Summer', now()->year . '-07-01', now()->year . '-07-03', [], [$me]);
        $this->album('Last year', (now()->year - 1) . '-07-01', (now()->year - 1) . '-07-03', [], [$me]);

        $this->travelTo(now()->setDate(now()->year, 11, 30));
        $this->actingAs($me)->get('/wrapped/' . now()->year)->assertRedirect('/wrapped');
        $this->actingAs($me)->get('/wrapped')->assertRedirect('/wrapped/' . (now()->year - 1));
        // Admins can look before
        $this->actingAs(tap($me)->update(['isAdmin' => 1]))->get('/wrapped/' . now()->year)->assertOk();
        $me->update(['isAdmin' => 0]);

        $this->travelTo(now()->setDate(now()->year, 12, 1));
        $this->actingAs($me)->get('/wrapped/' . now()->year)
            ->assertInertia(fn ($page) => $page->component('Wrapped')->where('wrapped.albumCount', 1));
        $this->actingAs($me)->get('/')->assertInertia(fn ($page) => $page->where('wrappedBanner', now()->year));
    }

    public function test_no_albums_no_wrapped(): void
    {
        $this->actingAs(User::factory()->create())->get('/wrapped')
            ->assertInertia(fn ($page) => $page->component('Wrapped')->where('wrapped', null)->where('years', []));
    }

    public function test_december_notification_goes_to_people_with_albums_that_year(): void
    {
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) extends PushSender {
            public function __construct(private array &$sent) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                $this->sent[] = $payload;

                return [];
            }
        });
        $this->travelTo(now()->setDate(2026, 12, 27));
        [$traveller, $stayedHome] = User::factory()->count(2)->create();
        $this->album('Summer', '2026-07-01', '2026-07-03', [], [$traveller]);
        $this->album('Long ago', '2020-07-01', '2020-07-03', [], [$stayedHome]);
        PushSubscription::create(['user_id' => $traveller->id, 'endpoint' => 'https://fcm.googleapis.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 'a']);

        $this->artisan('wrapped:send')->assertSuccessful()->expectsOutput('Wrapped 2026 sent to 1 person.');

        $this->assertSame('Your 2026 Wrapped is ready 🎁', $sent[0]['title']);
        $emails = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $emails);
        $this->assertSame($traveller->email, $emails->first()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    private function album(string $title, string $from, string $to, array $places, array $people): Listing
    {
        $album = Listing::factory()->create(['title' => $title, 'dateFrom' => $from, 'dateTo' => $to]);
        foreach ($places as $i => [$lat, $lng, $name]) {
            $album->places()->create(['latitude' => $lat, 'longitude' => $lng, 'place_name' => $name, 'position' => $i]);
        }
        $album->users()->attach(collect($people)->pluck('id'));

        return $album;
    }
}
