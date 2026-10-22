<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Facades\Mail;

// Trip news to its members: invitations, dates decided, "one week to go". Phone and/or email
class TripNotices
{
    public static function send(iterable $users, Trip $trip, string $title, string $text, string $button = 'Open the trip'): void
    {
        $users = collect($users)->reject(fn ($user) => $user->is_demo);
        Notify::push($users, 'trips', ['title' => $title, 'body' => $text, 'url' => "/trips/{$trip->id}", 'tag' => "trip-{$trip->id}"]);

        foreach ($users as $user) {
            if (! Notify::wants($user, 'trips', 'email')) {
                continue;
            }
            Mail::send('mails.trip_notice', [
                'name' => $user->name,
                'heading' => $title,
                'text' => $text,
                'trip' => $trip,
                'dates' => self::dates($trip),
                'link' => url("/trips/{$trip->id}"),
                'button' => $button,
            ], fn ($message) => $message->to($user->email, $user->name)->subject($title . ' - theboise.it')->from('info@theboise.it', 'TheBoise'));
        }
    }

    // "1–8 August 2027", "30 July – 2 August 2027", or null while the poll is open
    public static function dates(Trip $trip): ?string
    {
        if (! $trip->date_from) {
            return null;
        }
        $from = $trip->date_from;
        $to = $trip->date_to ?? $from;

        return match (true) {
            $from->isSameDay($to) => $from->format('j F Y'),
            $from->isSameMonth($to) => $from->format('j') . '–' . $to->format('j F Y'),
            $from->isSameYear($to) => $from->format('j F') . ' – ' . $to->format('j F Y'),
            default => $from->format('j F Y') . ' – ' . $to->format('j F Y'),
        };
    }
}
