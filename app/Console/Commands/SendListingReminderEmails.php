<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ListingUser;
use App\Console\Mail\ListingReminderMail;
use Illuminate\Support\Facades\Mail;

class SendListingReminderEmails extends Command
{
    protected $signature = 'listings:send-reminders';
    protected $description = 'Send reminder emails for unseen listings every 7 days';

    public function handle()
    {
        // Retrieve listing_user records with seen = false
        $listingUsers = ListingUser::with(['user', 'listing'])
            ->where('seen', false)
            ->get();
        
        $listingUsers->groupBy('user_id')->each(function ($groupedModels, $userId) {
            $user = $groupedModels->first()->user;
            $listings = $groupedModels->pluck('listing')->filter();

            // Not in an album nor on the site for 18 months: they've drifted away, no reminders
            if (! $user || $listings->isEmpty() || ! $user->isActive()) {
                return;
            }

            Mail::to($user)
                ->send(new ListingReminderMail($user, $listings));
        });

        $this->info('Reminder emails have been sent successfully.');
    }
}
