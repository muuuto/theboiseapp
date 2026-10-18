<?php

namespace App\Support;

use App\Console\Mail\AnniversaryReminderMail;
use App\Console\Mail\BirthdayReminderMail;
use App\Console\Mail\ListingReminderMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// Every email with sample data, to see how it looks: from the maintenance page (to the admin)
// or with "php artisan mails:preview". Nothing is saved and nobody else receives anything
class MailSamples
{
    public const TEMPLATES = [
        'new_album' => 'New album',
        'new_comment' => 'New comment on an album',
        'anniversary_reminder' => 'On this day (memories)',
        'listing_reminder' => 'Albums not opened yet',
        'birthday_reminder' => 'Happy birthday',
        'new_comment_post' => 'New comment on a forum post',
        'notify_post' => 'Forum post shared with everyone',
        'new_post' => 'New forum post (to the admin)',
        'welcome_email' => 'Welcome',
        'user_registered' => 'New user (to the admin)',
        'reset_password' => 'Password reset',
        'backup' => 'Database backup (without the file)',
    ];

    // $user is who the email pretends to be for: their name and their albums
    public static function send(string $template, string $to, User $user): void
    {
        $listings = self::albums($user);
        $album = $listings->first();
        $comment = '<p>What a trip! 😄<br>Who has the photos of the last evening?</p>';

        $mailable = match ($template) {
            'anniversary_reminder' => new AnniversaryReminderMail($user, $listings),
            'listing_reminder' => new ListingReminderMail($user, $listings),
            'birthday_reminder' => new BirthdayReminderMail($user, 'italian', 'Tanti auguri di buon compleanno', 30),
            default => null,
        };
        if ($mailable) {
            Mail::to($to)->send($mailable);

            return;
        }

        [$subject, $data] = match ($template) {
            'new_album' => ['New album ' . $album->title . ' added to theboise.it', ['name' => $user->name, 'albumTitle' => $album->title, 'listing' => $album]],
            'new_comment' => ['New comment added to album ' . $album->title . ' inside theboise.it', ['name' => $user->name, 'commentOfUser' => 'Zecca', 'comment' => $comment, 'albumTitle' => $album->title, 'albumLink' => url('/listings/' . $album->id)]],
            'new_comment_post' => ['New comment added to post Pizza night - theboise.it', ['name' => $user->name, 'commentOfUser' => 'Zecca', 'comment' => "I'm in!\nBringing the beer", 'postTitle' => 'Pizza night', 'postLink' => url('/forum')]],
            'notify_post' => ['Check out this new post inside forum: Pizza night - theboise.it', ['name' => $user->name, 'postTitle' => 'Pizza night', 'postLink' => url('/forum')]],
            'new_post' => ['New post - theboise.it', ['name' => 'Pizza night', 'postLink' => url('/forum')]],
            'welcome_email' => ['Welcome to theboise.it', ['name' => $user->name]],
            'user_registered' => ['New user - theboise.it', ['name' => 'Anna', 'email' => 'anna@example.com', 'note' => 'For Anna']],
            'reset_password' => ['Reset your theboise.it password', ['name' => $user->name, 'link' => url('/forgot-password'), 'minutes' => 60]],
            'backup' => ['TheBoise database backup ' . now()->format('Y-m-d'), ['file' => 'theboise-' . now()->format('Y-m-d') . '.sql.gz']],
        };

        Mail::send('mails.' . $template, $data, fn ($message) => $message->to($to)->subject('[Test] ' . $subject)->from('info@theboise.it', 'TheBoise'));
    }

    // Two albums with a preview, the user's own if possible; a made-up one on an empty site
    private static function albums(User $user)
    {
        $own = $user->exists ? $user->listing()->whereNotNull('logo')->latest('dateFrom')->take(2)->get() : collect();
        $listings = $own->isNotEmpty() ? $own : Listing::whereNotNull('logo')->latest('dateFrom')->take(2)->get();
        if ($listings->isEmpty()) {
            $listings = collect([new Listing(['id' => 0, 'title' => 'Sicilia 2026', 'location' => 'Palermo', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-08'])]);
        }

        return $listings->values()->each(fn ($listing, $i) => $listing['diffInYears'] = $i + 1);
    }
}
