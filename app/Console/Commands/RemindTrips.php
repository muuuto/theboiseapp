<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Support\TripNotices;
use App\Support\Weather;
use Illuminate\Console\Command;

// "One week to go" and "Tomorrow!" for the trips about to start, "Tomorrow!" for the events (not to
// who said no), with the forecast when there is one (every morning)
class RemindTrips extends Command
{
    protected $signature = 'trips:remind';
    protected $description = 'Remind the people going on a trip one week and one day before';

    public function handle()
    {
        foreach ([7 => ':trip: one week to go', 1 => ':trip: tomorrow!'] as $days => $when) {
            $trips = Trip::with(['members', 'items'])->whereNull('listing_id')->whereDate('date_from', now()->addDays($days)->toDateString())->get()
                ->reject(fn (Trip $trip) => $trip->isEvent() && $days !== 1);
            foreach ($trips as $trip) {
                $left = $trip->items->where('done', false)->count();
                $weather = $days === 1 ? self::forecast($trip) : '';
                if ($trip->isEvent()) {
                    TripNotices::send($trip->members->reject(fn ($member) => ($member->pivot->rsvp ?? null) === 'no'), $trip, fn () => [
                        __($when, ['trip' => $trip->title]),
                        trim(TripNotices::when($trip) . ($trip->destination ? " · {$trip->destination}" : '') . ' ' . $weather),
                        __('Open the event'),
                    ]);
                    continue;
                }
                TripNotices::send($trip->members, $trip, fn () => [
                    __($when, ['trip' => $trip->title]),
                    trim(($left
                        ? trans_choice(':count thing still to sort out on the list.|:count things still to sort out on the list.', $left)
                        : __('Everything on the list is done. Pack your bags!')) . ' ' . $weather),
                    __('See the list'),
                ]);
            }
            $this->line("{$trips->count()} trips starting in {$days} " . ($days === 1 ? 'day' : 'days'));
        }

        return self::SUCCESS;
    }

    // "☀️ 24° / 15°" for the first day, or nothing
    private static function forecast(Trip $trip): string
    {
        $day = Weather::forTrip($trip)['days'][0] ?? null;

        return $day ? Weather::emoji($day['code']) . " {$day['max']}° / {$day['min']}°" : '';
    }
}
