<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use App\Support\Locale;
use App\Support\TripNotices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Each person's calendar link: their trips and events (not the ones they said no to) and everyone's
// birthdays, for Google Calendar / iPhone / Outlook to subscribe to (they check it every few hours).
// The link is the key: no login, so it's long and random, and can be renewed
class CalendarController extends Controller
{
    public function feed(string $token)
    {
        abort_unless(Schema::hasColumn('users', 'calendar_token'), 404);
        $user = User::where('calendar_token', $token)->where('is_demo', false)->firstOrFail();
        $ics = Locale::for($user, fn () => $this->calendar($user));

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="theboise.ics"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // Account page: turn it on, or a new link (the old one stops working)
    public function renew(Request $request)
    {
        $request->user()->update(['calendar_token' => Str::random(48)]);

        return back()->with('message', 'Calendar link ready');
    }

    public function off(Request $request)
    {
        $request->user()->update(['calendar_token' => null]);

        return back()->with('message', 'Calendar link turned off');
    }

    private function calendar(User $user): string
    {
        $events = [];
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $hasEvents = Trip::hasEvents();

        foreach ($user->trips()->with('members')->whereNotNull('date_from')->get() as $trip) {
            $answer = $hasEvents ? $trip->members->firstWhere('id', $user->id)?->pivot->rsvp : null;
            if ($answer === 'no') {
                continue;
            }
            $lines = [
                'UID:trip-' . $trip->id . '@theboise.it',
                'DTSTAMP:' . $stamp,
                'SUMMARY:' . self::text(($trip->isEvent() ? '🎉 ' : '✈️ ') . $trip->title . ($answer === 'maybe' ? ' (' . __('maybe') . ')' : '')),
                'URL:' . url("/trips/{$trip->id}"),
                'DESCRIPTION:' . self::text(trim(($trip->notes ? $trip->notes . "\n\n" : '') . url("/trips/{$trip->id}"))),
            ];
            if ($trip->destination) {
                $lines[] = 'LOCATION:' . self::text($trip->destination);
            }
            if ($trip->isEvent() && $trip->start_time) {
                // At its time (Italian time), about three hours long
                $start = Carbon::parse($trip->date_from->toDateString() . ' ' . $trip->start_time, 'Europe/Rome')->utc();
                $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $start->copy()->addHours(3)->format('Ymd\THis\Z');
            } else {
                // Whole days (the end is the day after the last one)
                $lines[] = 'DTSTART;VALUE=DATE:' . $trip->date_from->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . ($trip->date_to ?? $trip->date_from)->copy()->addDay()->format('Ymd');
            }
            $events[] = $lines;
        }

        // Birthdays, every year
        foreach (User::where('is_demo', false)->whereNotNull('birthday')->get(['id', 'name', 'birthday']) as $person) {
            $birthday = Carbon::parse($person->birthday);
            $events[] = [
                'UID:birthday-' . $person->id . '@theboise.it',
                'DTSTAMP:' . $stamp,
                'SUMMARY:' . self::text('🎂 ' . __(':name\'s birthday', ['name' => $person->name])),
                'DTSTART;VALUE=DATE:' . $birthday->format('Ymd'),
                'DTEND;VALUE=DATE:' . $birthday->copy()->addDay()->format('Ymd'),
                'RRULE:FREQ=YEARLY',
                'TRANSP:TRANSPARENT',
            ];
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TheBoise//theboise.it//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:TheBoise',
            'X-WR-TIMEZONE:Europe/Rome',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];
        foreach ($events as $event) {
            $lines = [...$lines, 'BEGIN:VEVENT', ...$event, 'END:VEVENT'];
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($line) => self::fold($line), $lines)) . "\r\n";
    }

    // Commas, semicolons, backslashes and new lines written the calendar way
    private static function text(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $value);
    }

    // Lines of at most 75 bytes, the next part starting with a space (never cutting a letter in two)
    private static function fold(string $line): string
    {
        $parts = [];
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $parts[] = substr($line, 0, $cut);
            $line = ' ' . substr($line, $cut);
        }
        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
