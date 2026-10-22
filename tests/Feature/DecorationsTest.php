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
