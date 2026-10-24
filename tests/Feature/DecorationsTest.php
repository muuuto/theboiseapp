<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Decorations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Home page decorations: holidays (some moving), how long before, birthdays first
class DecorationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_holidays(): void
    {
        $this->assertSame('2026-04-05', Decorations::easter(2026)->toDateString());
        $this->assertSame('2027-03-28', Decorations::easter(2027)->toDateString());
        $this->assertSame(['2026-02-12', '2026-02-17'], $this->dates('carnival', 2026));
        // Ends on the first Sunday of October, or on 3 October
        $this->assertSame(['2026-09-19', '2026-10-04'], $this->dates('oktoberfest', 2026));
        $this->assertSame(['2027-09-18', '2027-10-03'], $this->dates('oktoberfest', 2027));
    }

    public function test_christmas_snow_from_two_weeks_before(): void
    {
        $user = User::factory()->create();

        $this->assertNull(Decorations::today($user, $this->day('2026-12-10')));
        $this->assertSame(['christmas', 'snow', '🎄 Christmas in 14 days'], $this->summary(Decorations::today($user, $this->day('2026-12-11'))));
        $this->assertSame('🎄 Merry Christmas!', Decorations::today($user, $this->day('2026-12-25'))['message']);
        $this->assertNull(Decorations::today($user, $this->day('2026-12-28')));
        // New Year's Eve, and still on 1 January
        $this->assertSame('new_year', Decorations::today($user, $this->day('2026-12-31'))['key']);
        $this->assertSame('new_year', Decorations::today($user, $this->day('2027-01-01'))['key']);
    }

    public function test_the_shorter_holiday_wins_and_switches_are_respected(): void
    {
        $user = User::factory()->create();
        $this->assertSame('pride', Decorations::today($user, $this->day('2026-06-02'))['key']);

        $this->save(['holidays' => ['republic' => ['on' => true, 'weeks' => 0]]]);
        $this->assertSame('republic', Decorations::today($user, $this->day('2026-06-02'))['key']);
        $this->assertSame('pride', Decorations::today($user, $this->day('2026-06-03'))['key']);

        // Halloween 3 weeks before, then everything off
        $this->save(['holidays' => ['halloween' => ['on' => true, 'weeks' => 3]]]);
        $this->assertSame('🎃 Halloween in 21 days', Decorations::today($user, $this->day('2026-10-10'))['message']);
        $this->save(['enabled' => false]);
        $this->assertNull(Decorations::today($user, $this->day('2026-10-31')));
    }

    public function test_birthdays_come_first(): void
    {
        $this->travelTo('2026-12-20 10:00');
        $me = User::factory()->create(['name' => 'Muto', 'birthday' => '1995-06-01']);
        $anna = User::factory()->create(['name' => 'Anna', 'birthday' => '1996-12-20']);
        $gone = User::factory()->create(['name' => 'Gone', 'birthday' => '1990-12-20']);
        $this->makeActive($me, $anna);

        // Christmas is on, but it's Anna's birthday (Gone isn't around any more: not shown)
        $this->assertSame(['birthday', ['🥳', '🎂', '🎉', '🎁'], "🎂 Today is Anna's birthday!"], $this->summary(Decorations::today($me)));
        $this->assertSame('🥳 Happy birthday, Anna!', Decorations::today($anna)['message']);
        // The demo account never sees birthdays: Christmas for it
        $this->assertSame('christmas', Decorations::today(User::factory()->create(['is_demo' => true]))['key']);

        $this->save(['birthdays' => false]);
        $this->assertSame('christmas', Decorations::today($me)['key']);
        $this->assertNotNull($gone);
    }

    public function test_home_page_and_admin_preview(): void
    {
        $this->travelTo('2026-10-25 10:00');
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page->where('decoration.key', 'halloween')->where('decoration.message', '🎃 Halloween in 6 days'));
        // Only admins preview another one
        $this->actingAs($user)->get('/?decoration=christmas')->assertInertia(fn ($page) => $page->where('decoration.key', 'halloween'));
        $this->actingAs($admin)->get('/?decoration=christmas')->assertInertia(fn ($page) => $page->where('decoration.key', 'christmas')->where('decoration.message', '🎄 Merry Christmas!'));
    }

    public function test_maintenance_saves_the_choices(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs(User::factory()->create())->put('/admin/maintenance/decorations', $this->form())->assertForbidden();

        $this->actingAs($admin)->put('/admin/maintenance/decorations', $this->form(['halloween' => ['on' => true, 'weeks' => 4]]))->assertSessionHas('message');

        $this->assertSame(['on' => true, 'weeks' => 4], Decorations::settings()['holidays']['halloween']);
        $this->actingAs($admin)->get('/admin/maintenance')->assertInertia(fn ($page) => $page->where('decorations.enabled', true)->has('decorations.list', count(Decorations::HOLIDAYS)));
    }

    public function test_admins_add_their_own_decorations(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $anniversary = ['label' => 'TheBoise anniversary', 'icon' => '', 'snow' => false, 'falling' => '🎈 🎉🥳 🎈', 'greeting' => 'Happy anniversary!', 'from' => '2026-11-07', 'to' => '2026-11-07', 'yearly' => true];

        $this->actingAs($user)->post('/admin/maintenance/decorations/custom', $anniversary)->assertForbidden();
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', $anniversary)->assertSessionHas('message', 'Decoration added');
        $custom = Decorations::settings()['custom'][0];
        // Each emoji once; without an icon, the first one
        $this->assertSame(['🎈', '🎉', '🥳'], $custom['falling']);
        $this->assertSame('🎈', $custom['icon']);
        $key = Decorations::CUSTOM . $custom['id'];

        // Every year, only on the day at first; then a week before, saved with the other switches
        $this->assertSame([$key, ['🎈', '🎉', '🥳'], '🎈 Happy anniversary!'], $this->summary(Decorations::today($user, $this->day('2027-11-07'))));
        $this->assertNull(Decorations::today($user, $this->day('2027-11-06')));
        $this->actingAs($admin)->put('/admin/maintenance/decorations', $this->form([$key => ['on' => true, 'weeks' => 1]]))->assertSessionHas('message');
        $this->assertSame('🎈 TheBoise anniversary in 6 days', Decorations::today($user, $this->day('2027-11-01'))['message']);
        // The switches don't change what it is
        $this->assertSame('Happy anniversary!', Decorations::settings()['custom'][0]['greeting']);

        $this->actingAs($admin)->get('/admin/maintenance')->assertInertia(fn ($page) => $page
            ->has('decorations.list', count(Decorations::HOLIDAYS) + 1)
            ->where('decorations.list', fn ($list) => collect($list)->firstWhere('key', $key)['custom'] === true));
        $this->actingAs($admin)->get("/?decoration={$key}")->assertInertia(fn ($page) => $page->where('decoration.message', '🎈 Happy anniversary!'));

        // Changed to snow, two days, only once
        $this->actingAs($admin)->put("/admin/maintenance/decorations/custom/{$custom['id']}", ['snow' => true, 'yearly' => false, 'from' => '2026-11-20', 'to' => '2026-11-21'] + $anniversary)
            ->assertSessionHas('message', 'Decoration saved');
        $this->assertSame([$key, 'snow', '❄️ Happy anniversary!'], $this->summary(Decorations::today($user, $this->day('2026-11-21'))));
        $this->assertNull(Decorations::today($user, $this->day('2027-11-21')));
        $this->assertSame(1, Decorations::settings()['custom'][0]['weeks']);

        // Nothing falling, too many emojis, the end before the start: not saved
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', ['falling' => ''] + $anniversary)->assertSessionHasErrors('falling');
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', ['falling' => '1 2 3 4 5 6 7 8 9'] + $anniversary)->assertSessionHasErrors('falling');
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', ['to' => '2026-11-01'] + $anniversary)->assertSessionHasErrors('to');
        $this->assertCount(1, Decorations::settings()['custom']);

        $this->actingAs($admin)->delete("/admin/maintenance/decorations/custom/{$custom['id']}")->assertSessionHas('message', 'Decoration deleted');
        $this->assertSame([], Decorations::settings()['custom']);
        $this->assertNull(Decorations::preview($key, $admin));
    }

    public function test_emojis_written_side_by_side_stay_whole(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', [
            'label' => 'Family day', 'falling' => '🇮🇹🎉👨‍👩‍👧👍🏽❤️🇮🇹', 'greeting' => 'Hi!', 'from' => '2026-05-10', 'to' => '2026-05-10',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['🇮🇹', '🎉', '👨‍👩‍👧', '👍🏽', '❤️'], Decorations::settings()['custom'][0]['falling']);
    }

    public function test_an_own_decoration_across_new_year_comes_back_every_year(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/maintenance/decorations/custom', [
            'label' => 'Ski week', 'icon' => '⛷️', 'falling' => '⛷️🏂', 'greeting' => 'Off to the mountains!', 'from' => '2026-12-27', 'to' => '2027-01-03', 'yearly' => true,
        ])->assertSessionHasNoErrors();
        $key = Decorations::CUSTOM . Decorations::settings()['custom'][0]['id'];

        $this->assertSame(['2030-12-27', '2031-01-03'], $this->dates($key, 2030));
        // Last year's still going on in January (New Year's Eve is shorter: it wins on its days)
        $this->assertSame($key, Decorations::today($admin, $this->day('2028-01-02'))['key']);
        $this->assertSame('new_year', Decorations::today($admin, $this->day('2027-12-31'))['key']);
        $this->assertSame('⛷️ Off to the mountains!', Decorations::today($admin, $this->day('2027-12-28'))['message']);
    }

    private function save(array $changes): void
    {
        $settings = array_replace_recursive(Decorations::settings(), $changes);
        \App\Support\Settings::set(Decorations::SETTING, $settings);
    }

    private function form(array $holidays = []): array
    {
        return array_replace_recursive(Decorations::settings(), ['holidays' => $holidays]);
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    private function dates(string $key, int $year): array
    {
        return array_map(fn ($day) => $day->toDateString(), Decorations::days($key, $year));
    }

    private function summary(?array $decoration): array
    {
        return [$decoration['key'], $decoration['falling'], $decoration['message']];
    }
}
