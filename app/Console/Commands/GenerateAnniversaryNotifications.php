<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Carbon\Carbon;
use App\Models\User;
use App\Console\Mail\AnniversaryReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Support\Media;
use App\Support\Notify;

class GenerateAnniversaryNotifications extends Command
{
    protected $signature = 'notifications:anniversaries {user?}';
    protected $description = 'Generate anniversary notifications for listings';

    public function handle()
    {
        $user = auth()->user();
        $todayMd = now()->format('m-d');

        if ($this->argument('user')) {
            $specificUser = true;
        } else {
            $specificUser = false;
        }

        $notifyUsersCollection = self::getMemoriesOfTheDay($specificUser);

        if ($specificUser) {
            if (isset($notifyUsersCollection[auth()->id()])) {
                Mail::to($user)
                    ->send(new AnniversaryReminderMail($user, collect($notifyUsersCollection[auth()->id()])));
            }
        } else {
            foreach ($notifyUsersCollection as $key => $userWithAlbums) {
                $user = User::where('id', '=', $key)->first();
                // Not in an album nor on the site for 18 months: no daily memories for them
                if (! $user || ! $user->isActive()) {
                    continue;
                }

                $filteredListings = collect($userWithAlbums)->filter(function ($listing) use ($todayMd) {
                    return Carbon::parse($listing->dateFrom)->format('m-d') === $todayMd;
                });

                if ($filteredListings->isNotEmpty()) {
                    $first = $filteredListings->first();
                    $more = $filteredListings->count() - 1;
                    Notify::push($user, 'memories', fn () => [
                        'title' => $more
                            ? __('On this day: :title and :count more', ['title' => $first->title, 'count' => $more])
                            : __('On this day: :title', ['title' => $first->title]),
                        'body' => trans_choice(':count year ago. Relive it!|:count years ago. Relive it!', $first->diffInYears),
                        'url' => '/listings/' . $first->id,
                        'tag' => 'memories-' . now()->toDateString(),
                        'image' => Media::url($first->logo),
                    ]);
                    if (Notify::wants($user, 'memories', 'email')) {
                        Mail::to($user)->send(new AnniversaryReminderMail($user, $filteredListings));
                    }
                }
            }
        }

        $this->info('Anniversary notifications generated.');
    }

    public static function getMemoriesOfTheDay(bool $userSpecific = false)
    {
        $today = now();
        $todayMonthDay = now()->format('m-d');
        $notifyUsersCollection = [];

        // Fetch listings created on this day in past years
        if ($userSpecific == false) {
            $listings = Listing::where(function ($query) use ($todayMonthDay) {
                $query->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') <= DATE_FORMAT(dateTo, '%m-%d')")
                    ->whereRaw("? BETWEEN DATE_FORMAT(dateFrom, '%m-%d') AND DATE_FORMAT(dateTo, '%m-%d')", [$todayMonthDay]);
            })->orWhere(function ($query) use ($todayMonthDay) {
                $query->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') > DATE_FORMAT(dateTo, '%m-%d')")
                    ->where(function ($q) use ($todayMonthDay) {
                        $q->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') <= ?", [$todayMonthDay])
                            ->orWhereRaw("DATE_FORMAT(dateTo, '%m-%d') >= ?", [$todayMonthDay]);
                    });
            })->get();
        } else {
            $listings = Listing::with('users')
                ->whereHas('users', function ($query) {
                    $query->where('id', auth()->id());
                })
                ->where(function ($query) use ($todayMonthDay) {
                    $query->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') <= DATE_FORMAT(dateTo, '%m-%d')")
                        ->whereRaw("? BETWEEN DATE_FORMAT(dateFrom, '%m-%d') AND DATE_FORMAT(dateTo, '%m-%d')", [$todayMonthDay]);
                })->orWhere(function ($query) use ($todayMonthDay) {
                    $query->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') > DATE_FORMAT(dateTo, '%m-%d')")
                        ->where(function ($q) use ($todayMonthDay) {
                            $q->whereRaw("DATE_FORMAT(dateFrom, '%m-%d') <= ?", [$todayMonthDay])
                                ->orWhereRaw("DATE_FORMAT(dateTo, '%m-%d') >= ?", [$todayMonthDay]);
                        });
                })
                ->get();
        }

        foreach ($listings as $listing) {
            foreach ($listing->users as $user) {
                // Initialize the user entry if it doesn't exist
                if (!isset($notifyUsersCollection[$user->id])) {
                    $notifyUsersCollection[$user->id] = [];
                }

                $albumDate = Carbon::createFromFormat('Y-m-d', $listing->dateFrom)->startOfDay();
                // Carbon 3 returns a float (e.g. 3.02), we want whole years
                $diffYears = (int) $albumDate->diffInYears($today);
                $listing['diffInYears'] = $diffYears;

                // Add this listing's title to the user's list
                $notifyUsersCollection[$user->id][] = $listing;
            }
        }

        return $notifyUsersCollection;
    }
}
