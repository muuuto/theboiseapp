<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Notify;
use App\Support\Wrapped;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// "Your 2026 Wrapped is ready": email and phone notification to everyone with albums that year
// (scheduled on 27 December; --user to send it to one person, e.g. to try it)
class SendWrapped extends Command
{
    protected $signature = 'wrapped:send {year? : the year, this one by default} {--user= : only this user id}';
    protected $description = 'Tell everyone their yearly Wrapped is ready';

    public function handle()
    {
        $year = (int) ($this->argument('year') ?? now()->year);
        $users = User::active()->where('is_demo', false)
            ->when($this->option('user'), fn ($query, $id) => $query->whereKey($id))
            ->whereHas('listing', fn ($query) => $query->whereYear('dateFrom', $year))
            ->get();

        foreach ($users as $user) {
            $wrapped = Wrapped::for($user, $year);
            Notify::push($user, 'wrapped', [
                'title' => "Your {$year} Wrapped is ready 🎁",
                'body' => "{$wrapped['albumCount']} albums, {$wrapped['daysAway']} days away, " . number_format($wrapped['km']) . ' km. Tap to see it all.',
                'url' => "/wrapped/{$year}",
                'tag' => "wrapped-{$year}",
            ]);
            if (Notify::wants($user, 'wrapped', 'email')) {
                Mail::send('mails.wrapped', ['name' => $user->name, 'wrapped' => $wrapped, 'link' => url("/wrapped/{$year}")], fn ($message) => $message
                    ->to($user->email, $user->name)
                    ->subject("Your {$year} on TheBoise 🎁")
                    ->from('info@theboise.it', 'TheBoise'));
            }
        }

        $this->info("Wrapped {$year} sent to {$users->count()} " . ($users->count() === 1 ? 'person' : 'people') . '.');

        return self::SUCCESS;
    }
}
