<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Carbon\Carbon;
use App\Models\User;
use App\Console\Mail\AnniversaryReminderMail;
use Illuminate\Support\Facades\Mail;

class GenerateAnniversaryNotifications extends Command
{
    protected $signature = 'notifications:anniversaries {user?}';
    protected $description = 'Generate anniversary notifications for listings';

    public function handle()
    {
        $user = auth()->user();

        if ($this->argument('user')) {
            $specificUser = true;
        } else {
            $specificUser = false;
        }

        $notifyUsersCollection = self::getMemoriesOfTheDay($specificUser);

        if ($specificUser) {
            if (isset($notifyUsersCollection[auth()->id()])) {
                Mail::to($user->email)
                    ->send(new AnniversaryReminderMail($user, collect($notifyUsersCollection[auth()->id()])));
            }
        } else {
            foreach ($notifyUsersCollection as $key => $userWithAlbums) {
                $user = User::where('id', '=', $key)->first();

                Mail::to($user->email)
                    ->send(new AnniversaryReminderMail($user, collect($userWithAlbums)));
            }
        }

        $this->info('Anniversary notifications generated.');
    }

    public function getMemoriesOfTheDay(bool $userSpecific = false)
    {
        $today = now();
        $notifyUsersCollection = [];

        // Fetch listings created on this day in past years
        if ($userSpecific == false) {
            $listings = Listing::whereMonth('dateFrom', $today->month)
                ->whereDay('dateFrom', $today->day)->get();
        } else {
            $listings = Listing::with('users')
                ->whereHas('users', function ($query) {
                    $query->where('id', auth()->id());
                })
                ->whereMonth('dateFrom', $today->month)
                ->whereDay('dateFrom', $today->day)
                ->get();
        }

        foreach ($listings as $listing) {
            foreach ($listing->users as $user) {
                // Initialize the user entry if it doesn't exist
                if (!isset($notifyUsersCollection[$user->id])) {
                    $notifyUsersCollection[$user->id] = [];
                }

                $albumDate = Carbon::createFromFormat('Y-m-d', $listing->dateFrom);
                $diffYears = $albumDate->diffInYears($today);
                $listing['diffInYears'] = $diffYears;

                // Add this listing's title to the user's list
                $notifyUsersCollection[$user->id][] = $listing;
            }
        }

        return $notifyUsersCollection;
    }
}
