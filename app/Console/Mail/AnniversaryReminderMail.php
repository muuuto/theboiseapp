<?php

namespace App\Console\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Collection; 
use Illuminate\Support\Carbon;

class AnniversaryReminderMail extends Mailable
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
        $todayMd = Carbon::today()->format('m-d');

        $this->listings = $this->listings->filter(function ($listing) use ($todayMd) {
            return Carbon::parse($listing->dateFrom)->format('m-d') === $todayMd;
        });

        if ($this->listings->isEmpty()) {
            return $this; // This prevents the email from being sent
        }

        return $this
            ->subject('On this day, many years ago')
            ->from('info@theboise.it', 'TheBoise')
            ->view('mails.anniversary_reminder');
    }
}
