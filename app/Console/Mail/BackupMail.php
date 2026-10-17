<?php

namespace App\Console\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupMail extends Mailable
{
    use Queueable, SerializesModels;

    public $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function build()
    {
        return $this
            ->subject('TheBoise database backup ' . now()->format('Y-m-d'))
            ->from('info@theboise.it', 'TheBoise')
            ->view('mails.backup')
            ->attach($this->file);
    }
}
