<?php

namespace App\Console\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class BirthdayReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $key;
    public $greeting;
    public $difference;

    public function __construct(
        User $user,
        string $key,
        string $greeting,
        int $difference
    ) {
        $this->user = $user;
        $this->key = $key;
        $this->greeting = $greeting;
        $this->difference = $difference;
    }

    public function build()
    {
        return $this
            ->subject('Happy Birthday from TheBoise!')
            ->from('info@theboise.it', 'TheBoise')
            ->view('mails.birthday_reminder');
    }
}
