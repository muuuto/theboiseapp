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
    public $age;

    public function __construct(
        User $user,
        string $key,
        string $greeting,
        int $age
    ) {
        $this->user = $user;
        $this->key = $key;
        $this->greeting = $greeting;
        $this->age = $age;
    }

    public function build()
    {
        return $this
            ->subject(__('Happy Birthday from TheBoise!'))
            ->from('info@theboise.it', 'TheBoise')
            ->view('mails.birthday_reminder');
    }
}
