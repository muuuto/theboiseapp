<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\MailSamples;
use Illuminate\Console\Command;

// Sends one copy of every email with sample data to one address (locally they land in Mailpit)
class PreviewMails extends Command
{
    protected $signature = 'mails:preview {to : the address that receives every email}';
    protected $description = 'Send a sample of every email to one address';

    public function handle()
    {
        $user = User::where('isAdmin', 1)->where('is_demo', false)->first() ?? new User(['name' => 'Anna']);

        foreach (array_keys(MailSamples::TEMPLATES) as $template) {
            MailSamples::send($template, $this->argument('to'), $user);
            $this->line("Sent {$template}");
        }

        $this->info('Every email was sent to ' . $this->argument('to') . '.');

        return self::SUCCESS;
    }
}
