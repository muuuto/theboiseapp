<?php

namespace App\Support;

use Minishlink\WebPush\VAPID;

// The keys that sign phone notifications ("they really come from theboise.it").
// Made on the server the first time they're needed and kept in the database (settings), so the
// private one never leaves the server. Changing them would turn off notifications on every device
class VapidKeys
{
    public const SETTING = 'vapid_keys';

    // ['publicKey' => ..., 'privateKey' => ...], or null before the settings migration runs
    public static function get(): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return null;
        }

        $keys = Settings::get(self::SETTING);
        if (! $keys) {
            $keys = VAPID::createVapidKeys();
            Settings::set(self::SETTING, $keys);
        }

        return $keys;
    }

    public static function publicKey(): ?string
    {
        return self::get()['publicKey'] ?? null;
    }
}
