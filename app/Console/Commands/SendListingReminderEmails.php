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

        foreach ($listingUsers as $listingUser) {
            // Send reminder email
            Mail::to($listingUser->user->email)
                ->send(new ListingReminderMail($listingUser->user, $listingUser->listing));
        }

        $this->info('Reminder emails have been sent successfully.');
    }
}
