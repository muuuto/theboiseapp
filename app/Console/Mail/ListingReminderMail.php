<?php

namespace App\Console\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Listing;

class ListingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $listing;

    public function __construct(User $user, Listing $listing)
    {
        $this->user = $user;
        $this->listing = $listing;
    }

    public function build()
    {
        return $this
            ->subject('Reminder: Check your new Album')
            ->view('mails.listing_reminder');
    }
}
