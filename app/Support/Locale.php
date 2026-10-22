<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

// The site in English (default) or Italian: each person's choice, also for their emails and
// notifications. Texts are written in English and translated in lang/it.json
class Locale
{
    public const AVAILABLE = ['en' => 'English', 'it' => 'Italiano'];
    public const DEFAULT = 'en';

    public static function of(?User $user): string
    {
        $locale = $user?->locale;

        return isset(self::AVAILABLE[$locale]) ? $locale : self::DEFAULT;
    }

    public static function set(string $locale): void
    {
        App::setLocale($locale);
        Carbon::setLocale($locale);
    }

    // Runs something (an email, a notification) in someone's language, then goes back
    public static function for(?User $user, callable $callback): mixed
    {
        $previous = App::getLocale();
        self::set(self::of($user));
        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
