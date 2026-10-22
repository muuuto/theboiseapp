<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

// Home page decorations: things falling from the sky on holidays (snow at Christmas, pumpkins at
// Halloween...) and on someone's birthday. Switched on/off and timed from the maintenance page
class Decorations
{
    public const SETTING = 'decorations';

    // key => label, what falls ('snow' or emojis), greeting on the day(s), default weeks before, default on
    public const HOLIDAYS = [
        'christmas' => ['label' => 'Christmas', 'icon' => '🎄', 'falling' => 'snow', 'greeting' => 'Merry Christmas!', 'weeks' => 2, 'on' => true],
        'new_year' => ['label' => 'New Year', 'icon' => '🎆', 'falling' => ['🎆', '🥂', '🍾', '🎉', '✨'], 'greeting' => 'Happy New Year!', 'weeks' => 0, 'on' => true],
        'epiphany' => ['label' => 'Befana', 'icon' => '🧹', 'falling' => ['🧹', '🍬', '🧦', '🍫'], 'greeting' => 'La Befana vien di notte!', 'weeks' => 0, 'on' => true],
        'valentine' => ['label' => 'Valentine\'s Day', 'icon' => '💘', 'falling' => ['❤️', '💘', '🌹', '💌'], 'greeting' => 'Happy Valentine\'s Day!', 'weeks' => 0, 'on' => true],
        'womens_day' => ['label' => 'Women\'s Day', 'icon' => '💛', 'falling' => ['💛', '🌼', '💐'], 'greeting' => 'Happy Women\'s Day!', 'weeks' => 0, 'on' => false],
        'st_patrick' => ['label' => 'St Patrick\'s Day', 'icon' => '🍀', 'falling' => ['🍀', '🍺', '☘️'], 'greeting' => 'Happy St Patrick\'s Day!', 'weeks' => 0, 'on' => false],
        'carnival' => ['label' => 'Carnival', 'icon' => '🎭', 'falling' => ['🎭', '🎊', '🤡', '🎉'], 'greeting' => 'A Carnevale ogni scherzo vale!', 'weeks' => 0, 'on' => true],
        'easter' => ['label' => 'Easter', 'icon' => '🐣', 'falling' => ['🐣', '🥚', '🐰', '🌷'], 'greeting' => 'Happy Easter!', 'weeks' => 1, 'on' => true],
        'liberation' => ['label' => 'Liberation Day', 'icon' => '🇮🇹', 'falling' => ['🇮🇹', '🌹'], 'greeting' => 'Buona Festa della Liberazione!', 'weeks' => 0, 'on' => false],
        'republic' => ['label' => 'Festa della Repubblica', 'icon' => '🇮🇹', 'falling' => ['🇮🇹', '🎉'], 'greeting' => 'Buona Festa della Repubblica!', 'weeks' => 0, 'on' => false],
        'pride' => ['label' => 'Pride Month', 'icon' => '🏳️‍🌈', 'falling' => ['🏳️‍🌈', '❤️', '🧡', '💛', '💚', '💙', '💜'], 'greeting' => 'Happy Pride Month!', 'weeks' => 0, 'on' => true],
        'ferragosto' => ['label' => 'Ferragosto', 'icon' => '🏖️', 'falling' => ['☀️', '🍉', '🏖️', '🍦'], 'greeting' => 'Buon Ferragosto!', 'weeks' => 0, 'on' => true],
        'oktoberfest' => ['label' => 'Oktoberfest', 'icon' => '🍺', 'falling' => ['🍺', '🥨', '🍻'], 'greeting' => 'O\'zapft is!', 'weeks' => 0, 'on' => true],
        'halloween' => ['label' => 'Halloween', 'icon' => '🎃', 'falling' => ['🎃', '☠️', '⚰️', '👻', '🦇'], 'greeting' => 'Happy Halloween!', 'weeks' => 2, 'on' => true],
    ];

    public const BIRTHDAY = ['🥳', '🎂', '🎉', '🎁'];

    // The saved choices, filled in with the defaults
    public static function settings(): array
    {
        $saved = Settings::get(self::SETTING, []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'birthdays' => (bool) ($saved['birthdays'] ?? true),
            'holidays' => collect(self::HOLIDAYS)->map(fn ($holiday, $key) => [
                'on' => (bool) ($saved['holidays'][$key]['on'] ?? $holiday['on']),
                'weeks' => (int) ($saved['holidays'][$key]['weeks'] ?? $holiday['weeks']),
            ])->all(),
        ];
    }

    // [first day, last day] of a holiday in a year (the moving ones calculated)
    public static function days(string $key, int $year): array
    {
        $date = fn (int $month, int $day) => CarbonImmutable::create($year, $month, $day);
        $easter = self::easter($year);

        return match ($key) {
            'christmas' => [$date(12, 25), $date(12, 26)],
            'new_year' => [$date(12, 31), $date(12, 31)->addDay()],
            'epiphany' => [$date(1, 6), $date(1, 6)],
            'valentine' => [$date(2, 14), $date(2, 14)],
            'womens_day' => [$date(3, 8), $date(3, 8)],
            'st_patrick' => [$date(3, 17), $date(3, 17)],
            // Giovedì grasso to Martedì grasso
            'carnival' => [$easter->subDays(52), $easter->subDays(47)],
            // Easter Sunday and Pasquetta
            'easter' => [$easter, $easter->addDay()],
            'liberation' => [$date(4, 25), $date(4, 25)],
            'republic' => [$date(6, 2), $date(6, 2)],
            'pride' => [$date(6, 1), $date(6, 30)],
            'ferragosto' => [$date(8, 15), $date(8, 15)],
            'oktoberfest' => self::oktoberfest($year),
            'halloween' => [$date(10, 31), $date(10, 31)],
        };
    }

    // What the home page shows today for this person, or null
    public static function today(User $viewer, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();
        $settings = self::settings();
        if (! $settings['enabled']) {
            return null;
        }

        if ($settings['birthdays'] && ($birthday = self::birthday($viewer, $today))) {
            return $birthday;
        }

        // Every holiday on now (this year's, or last year's New Year still going on 1 January)
        $candidates = collect();
        foreach (self::HOLIDAYS as $key => $holiday) {
            if (! $settings['holidays'][$key]['on']) {
                continue;
            }
            foreach ([$today->year, $today->year - 1] as $year) {
                [$first, $last] = self::days($key, $year);
                $from = $first->subWeeks($settings['holidays'][$key]['weeks']);
                if ($today->betweenIncluded($from, $last)) {
                    $candidates->push(['key' => $key, 'first' => $first, 'last' => $last]);
                }
            }
        }
        // The shortest one wins (Festa della Repubblica on 2 June, inside Pride Month)
        $chosen = $candidates->sortBy(fn ($candidate) => [$candidate['first']->diffInDays($candidate['last']), $candidate['first']])->first();

        return $chosen ? self::holiday($chosen['key'], $chosen['first'], $chosen['last'], $today) : null;
    }

    // For the "Preview" links of the maintenance page: as if it were the first day of that holiday
    public static function preview(string $key, User $viewer): ?array
    {
        if ($key === 'birthday') {
            return ['key' => 'birthday', 'falling' => self::BIRTHDAY, 'message' => '🎂 Today is ' . $viewer->name . '\'s birthday!', 'season' => 'preview'];
        }
        if (! isset(self::HOLIDAYS[$key])) {
            return null;
        }
        [$first, $last] = self::days($key, now()->year);

        return self::holiday($key, $first, $last, $first);
    }

    private static function holiday(string $key, CarbonImmutable $first, CarbonImmutable $last, CarbonImmutable $today): array
    {
        $holiday = self::HOLIDAYS[$key];
        $daysLeft = (int) $today->diffInDays($first);

        return [
            'key' => $key,
            'falling' => $holiday['falling'],
            'message' => $today->lt($first)
                ? $holiday['icon'] . ' ' . $holiday['label'] . ' in ' . $daysLeft . ' ' . ($daysLeft === 1 ? 'day' : 'days')
                : $holiday['icon'] . ' ' . $holiday['greeting'],
            // To remember "hidden" until this holiday is over
            'season' => $key . '-' . $last->toDateString(),
        ];
    }

    // Birthdays today of people still around (never shown to the demo account)
    private static function birthday(User $viewer, CarbonImmutable $today): ?array
    {
        if ($viewer->is_demo) {
            return null;
        }
        $mine = $viewer->birthday && Carbon::parse($viewer->birthday)->format('m-d') === $today->format('m-d');
        $names = User::active()->where('is_demo', false)->where('id', '!=', $viewer->id)
            ->whereMonth('birthday', $today->month)->whereDay('birthday', $today->day)
            ->orderBy('name')->pluck('name');

        if (! $mine && $names->isEmpty()) {
            return null;
        }

        return [
            'key' => 'birthday',
            'falling' => self::BIRTHDAY,
            'message' => $mine
                ? '🥳 Happy birthday, ' . $viewer->name . '!' . ($names->isNotEmpty() ? ' (and ' . $names->join(', ', ' and ') . ' too)' : '')
                : '🎂 Today is ' . $names->join(', ', ' and ') . '\'s birthday!',
            'season' => 'birthday-' . $today->toDateString(),
        ];
    }

    // Easter Sunday (anonymous Gregorian algorithm: no PHP extension needed)
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }

    // Starts on the Saturday 15 days before the first Sunday of October, and ends on that Sunday
    // (or on 3 October, German Unity Day, when that comes later)
    private static function oktoberfest(int $year): array
    {
        $october = CarbonImmutable::create($year, 10, 1);
        $firstSunday = $october->isSunday() ? $october : $october->next(Carbon::SUNDAY);
        $end = $firstSunday->day < 3 ? CarbonImmutable::create($year, 10, 3) : $firstSunday;

        return [$firstSunday->subDays(15), $end];
    }
}
