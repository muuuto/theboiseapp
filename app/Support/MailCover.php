<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

// Album previews in emails: a JPEG copy (Outlook can't show WebP), behind a signed link so it
// can't be guessed (emails can't send the login cookie, so the link itself is the key)
class MailCover
{
    public static function url(?Listing $listing): ?string
    {
        return $listing?->logo ? URL::signedRoute('mail.cover', ['listing' => $listing->id]) : null;
    }

    // Path of the JPEG copy in the local (private) disk, made the first time it's asked for
    public static function jpeg(Listing $listing): ?string
    {
        if (! $listing->logo || ! Storage::disk('public')->exists($listing->logo)) {
            return null;
        }

        $path = 'mail-covers/' . sha1($listing->logo) . '.jpg';
        if (! Storage::disk('local')->exists($path)) {
            $image = imagecreatefromstring(Storage::disk('public')->get($listing->logo));
            if (! $image) {
                return null;
            }
            // Old previews have transparent bars: cut them, then flatten what's left on white (JPEG has no transparency)
            $image = AlbumCover::crop($image) ?? $image;
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            // Emails show it at most 600px wide: 1200 for sharp phone screens
            if (imagesx($flat) > 1200) {
                $flat = imagescale($flat, 1200);
            }
            ob_start();
            imagejpeg($flat, null, 82);
            Storage::disk('local')->put($path, ob_get_clean());
        }

        return Storage::disk('local')->path($path);
    }
}
