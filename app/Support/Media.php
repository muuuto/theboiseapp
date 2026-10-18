<?php

namespace App\Support;

// Public URL of an uploaded file (album previews, avatars, forum covers...).
// Production serves uploads at /storage/app/public/... (see the storage link question in TODO.md):
// when that is cleaned up, only this method has to change.
class Media
{
    public static function url(?string $path): ?string
    {
        return $path ? asset('storage/app/public/' . $path) : null;
    }
}
