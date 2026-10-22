<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Site switches saved in the database, changed from the maintenance page.
// Before their migration runs they all have their default value
class Settings
{
    public const MEMBERS_CAN_POST_ALBUMS = 'members_can_post_albums';

    // The forum post linked at the top of the home page (news, changelog), or null
    public const PINNED_POST = 'pinned_post';

    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$cache ??= Schema::hasTable('settings') ? DB::table('settings')->pluck('value', 'key')->all() : [];

        return array_key_exists($key, self::$cache) ? json_decode(self::$cache[$key], true) : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
