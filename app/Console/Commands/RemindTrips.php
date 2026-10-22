<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Support\TripNotices;
use Illuminate\Console\Command;

// "One week to go" and "Tomorrow!" for the trips about to start (every morning)
class RemindTrips extends Command
{
    protected $signature = 'trips:remind';
    protected $description = 'Remind the people going on a trip one week and one day before';

    public function handle()
    {
        foreach ([7 => ':trip: one week to go', 1 => ':trip: tomorrow!'] as $days => $when) {
            $trips = Trip::with(['members', 'items'])->whereNull('listing_id')->whereDate('date_from', now()->addDays($days)->toDateString())->get();
            foreach ($trips as $trip) {
                $left = $trip->items->where('done', false)->count();
                TripNotices::send($trip->members, $trip, fn () => [
                    __($when, ['trip' => $trip->title]),
                    $left
                        ? trans_choice(':count thing still to sort out on the list.|:count things still to sort out on the list.', $left)
                        : __('Everything on the list is done. Pack your bags!'),
                    __('See the list'),
                ]);
            }
            $this->line("{$trips->count()} trips starting in {$days} " . ($days === 1 ? 'day' : 'days'));
        }

        return self::SUCCESS;
    }
}
