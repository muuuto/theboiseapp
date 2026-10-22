<?php

namespace App\Console\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Collection; 

class ListingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $listings;

    public function __construct(User $user, Collection $listings)
    {
        $this->user = $user;
        $this->listings = $listings;
    }

    public function build()
    {
        return $this
            ->subject(__('Reminder: you have new albums to see'))
            ->from('info@theboise.it', 'TheBoise')
            ->view('mails.listing_reminder');
    }
}
