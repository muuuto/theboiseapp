<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Facades\Mail;

// Trip news to its members: invitations, dates decided, "one week to go". Phone and/or email
class TripNotices
{
    // $message: fn () => [title, text, button?], called in each person's language
    public static function send(iterable $users, Trip $trip, callable $message): void
    {
        foreach (collect($users)->reject(fn ($user) => $user->is_demo) as $user) {
            Locale::for($user, function () use ($user, $trip, $message) {
                [$title, $text, $button] = $message() + [2 => $trip->isEvent() ? __('Open the event') : __('Open the trip')];
                Notify::push($user, 'trips', ['title' => $title, 'body' => $text, 'url' => "/trips/{$trip->id}", 'tag' => "trip-{$trip->id}"]);
                if (! Notify::wants($user, 'trips', 'email')) {
                    return;
                }
                Mail::send('mails.trip_notice', [
                    'name' => $user->name,
                    'heading' => $title,
                    'text' => $text,
                    'trip' => $trip,
                    'dates' => self::when($trip),
                    'link' => url("/trips/{$trip->id}"),
                    'button' => $button,
                ], fn ($mail) => $mail->to($user->email, $user->name)->subject($title . ' - theboise.it')->from('info@theboise.it', 'TheBoise'));
            });
        }
    }

    // An event's day with its time: "12 December 2026, 20:30"
    public static function when(Trip $trip): ?string
    {
        $dates = self::dates($trip);

        return $dates && $trip->start_time ? $dates . ', ' . substr($trip->start_time, 0, 5) : $dates;
    }

    // "1–8 August 2027", "30 July – 2 August 2027", or null while the poll is open
    public static function dates(Trip $trip): ?string
    {
        if (! $trip->date_from) {
            return null;
        }
        $from = $trip->date_from;
        $to = $trip->date_to ?? $from;

        // Month names in the current language
        return match (true) {
            $from->isSameDay($to) => $from->translatedFormat('j F Y'),
            $from->isSameMonth($to) => $from->format('j') . '–' . $to->translatedFormat('j F Y'),
            $from->isSameYear($to) => $from->translatedFormat('j F') . ' – ' . $to->translatedFormat('j F Y'),
            default => $from->translatedFormat('j F Y') . ' – ' . $to->translatedFormat('j F Y'),
        };
    }
}
